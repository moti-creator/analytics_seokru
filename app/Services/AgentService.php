<?php

namespace App\Services;

use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Agentic report builder — uses function-calling LLM (Gemini or Groq/Llama)
 * to decide which GA4/GSC queries to run, then narrate the result.
 *
 * Tries Gemini first. On 429 rate limit, switches to Groq for the entire run.
 */
class AgentService
{
    protected const MAX_ITERATIONS = 8;

    /** @var 'gemini'|'groq' — Groq preferred (faster, higher free tier) */
    protected string $backend;

    protected function pickBackend(): string
    {
        return (new GroqService())->available() ? 'groq' : 'gemini';
    }

    public function __construct(public Connection $conn) {}

    public function run(string $userPrompt): array
    {
        $this->backend = $this->pickBackend();
        $google = new GoogleService($this->conn);
        $toolCalls = [];
        $systemPrompt = $this->systemPrompt();

        // Initialize history in backend-native format
        $geminiHistory = [
            ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\nUser request:\n" . $userPrompt]]],
        ];
        $groqHistory = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $parsed = $this->callLlm($geminiHistory, $groqHistory);

            // Rate limit triggered switch — retry this iteration
            if ($parsed['switched'] ?? false) {
                $parsed = $this->callLlm($geminiHistory, $groqHistory);
            }

            // Tool call requested
            if ($parsed['type'] === 'tool_call') {
                $fn = $parsed['name'];
                $args = $parsed['args'];
                $result = $this->executeTool($fn, $args, $google);

                $toolCalls[] = ['tool' => $fn, 'args' => $args, 'result_summary' => $this->summarize($result)];
                $resultJson = json_encode($result);

                // Append to Gemini history
                $geminiHistory[] = ['role' => 'model', 'parts' => [[
                    'functionCall' => ['name' => $fn, 'args' => (object) $args],
                ]]];
                $geminiHistory[] = ['role' => 'user', 'parts' => [[
                    'functionResponse' => [
                        'name' => $fn,
                        'response' => (object) ['result' => $result],
                    ],
                ]]];

                // Append to Groq history
                $toolCallId = 'call_' . $i . '_' . $fn;
                $groqHistory[] = ['role' => 'assistant', 'tool_calls' => [[
                    'id' => $toolCallId,
                    'type' => 'function',
                    'function' => ['name' => $fn, 'arguments' => json_encode($args)],
                ]]];
                $groqHistory[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => $resultJson,
                ];
                continue;
            }

            // Final text response
            $text = $parsed['text'] ?? '';
            if (trim(strip_tags($text)) === '') {
                Log::warning('Agent empty narrative', [
                    'prompt' => $userPrompt,
                    'backend' => $this->backend,
                    'tool_calls' => $toolCalls,
                ]);
                $narrative = '<p class="error-narrative">Agent returned no narrative (backend: ' . $this->backend . ').</p>';
                $narrative .= '<p>You can retry — sometimes the model stalls mid-stream.</p>';
                return [
                    'narrative' => $narrative,
                    'tool_calls' => $toolCalls,
                    'iterations' => $i + 1,
                    'error' => true,
                ];
            }
            return [
                'narrative' => $text,
                'tool_calls' => $toolCalls,
                'iterations' => $i + 1,
                'backend' => $this->backend,
            ];
        }

        return [
            'narrative' => '<p class="error-narrative">Agent exceeded max iterations without finalizing.</p>',
            'tool_calls' => $toolCalls,
            'iterations' => self::MAX_ITERATIONS,
            'error' => true,
        ];
    }

    /**
     * Unified LLM call. Returns normalized result:
     * ['type' => 'tool_call', 'name' => ..., 'args' => [...]]
     * ['type' => 'text', 'text' => '...']
     * ['switched' => true] if backend changed mid-call (caller should retry)
     */
    protected function callLlm(array $geminiHistory, array $groqHistory): array
    {
        if ($this->backend === 'groq') {
            return $this->callGroq($groqHistory);
        }
        return $this->callGemini($geminiHistory, $groqHistory);
    }

    protected function callGemini(array $geminiHistory, array $groqHistory): array
    {
        $model = config('services.gemini.model');
        $key = config('services.gemini.key');

        $resp = Http::timeout(90)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            [
                'contents' => $geminiHistory,
                'tools' => [['functionDeclarations' => $this->toolDefs()]],
                'generationConfig' => ['temperature' => 0.3],
            ]
        );

        if ($resp->status() === 429) {
            Log::info('Agent: Gemini 429 → switching to Groq');
            $groq = new GroqService();
            if ($groq->available()) {
                $this->backend = 'groq';
                return ['switched' => true];
            }
            return ['type' => 'text', 'text' => '<p class="error-narrative">Rate limit hit. No fallback LLM configured.</p>'];
        }

        if (!$resp->ok()) {
            Log::warning('Gemini error', ['body' => $resp->body()]);
            return ['type' => 'text', 'text' => '<p>LLM error: ' . e($resp->body()) . '</p>'];
        }

        $json = $resp->json();
        $part = $json['candidates'][0]['content']['parts'][0] ?? [];

        if (isset($part['functionCall'])) {
            return [
                'type' => 'tool_call',
                'name' => $part['functionCall']['name'],
                'args' => $part['functionCall']['args'] ?? [],
            ];
        }
        return ['type' => 'text', 'text' => $part['text'] ?? ''];
    }

    protected function callGroq(array $groqHistory): array
    {
        $groq = new GroqService();
        $tools = GroqService::convertToolDefs($this->toolDefs());
        $resp = $groq->chat($groqHistory, $tools);

        $msg = $resp['choices'][0]['message'] ?? [];

        if (!empty($msg['tool_calls'])) {
            $tc = $msg['tool_calls'][0];
            $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            return [
                'type' => 'tool_call',
                'name' => $tc['function']['name'],
                'args' => $args,
            ];
        }
        return ['type' => 'text', 'text' => $msg['content'] ?? ''];
    }

    // --- Tool execution (unchanged) ---

    protected function executeTool(string $fn, array $args, GoogleService $google): array
    {
        try {
            return match ($fn) {
                'ga4_query' => $this->ga4Query($google, $args),
                'gsc_query' => $this->gscQuery($google, $args),
                'today_date' => ['today' => now()->toDateString()],
                'make_chart' => $this->makeChart($args),
                default => ['error' => "Unknown tool: $fn"],
            };
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function ga4Query(GoogleService $google, array $args): array
    {
        $pid = $this->conn->ga4_property_id;
        if (!$pid) return ['error' => 'No GA4 property selected.'];

        $body = [
            'dateRanges' => [[
                'startDate' => $args['date_start'] ?? now()->subDays(7)->toDateString(),
                'endDate' => $args['date_end'] ?? now()->subDay()->toDateString(),
            ]],
            'metrics' => array_map(fn($m) => ['name' => $m], $args['metrics'] ?? ['sessions']),
            'limit' => min((int)($args['limit'] ?? 25), 100),
        ];

        if (!empty($args['dimensions'])) {
            $body['dimensions'] = array_map(fn($d) => ['name' => $d], $args['dimensions']);
        }
        if (!empty($args['order_by_metric'])) {
            $body['orderBys'] = [[
                'metric' => ['metricName' => $args['order_by_metric']],
                'desc' => ($args['order_by_desc'] ?? true),
            ]];
        }

        $resp = Http::withToken($google->publicToken())
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$pid}:runReport", $body)
            ->json();

        return $this->shrinkGa4Response($resp);
    }

    protected function gscQuery(GoogleService $google, array $args): array
    {
        $site = $this->conn->gsc_site_url;
        if (!$site) return ['error' => 'No GSC site selected.'];

        $body = [
            'startDate' => $args['date_start'] ?? now()->subDays(28)->toDateString(),
            'endDate' => $args['date_end'] ?? now()->subDays(3)->toDateString(),
            'dimensions' => $args['dimensions'] ?? [],
            'rowLimit' => min((int)($args['limit'] ?? 25), 500),
        ];

        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site) . '/searchAnalytics/query';
        $resp = Http::withToken($google->publicToken())->post($url, $body)->json();

        return [
            'rows' => array_slice($resp['rows'] ?? [], 0, $body['rowLimit']),
            'row_count' => count($resp['rows'] ?? []),
        ];
    }

    protected function shrinkGa4Response(array $resp): array
    {
        $headers = array_merge(
            array_map(fn($d) => $d['name'], $resp['dimensionHeaders'] ?? []),
            array_map(fn($m) => $m['name'], $resp['metricHeaders'] ?? [])
        );

        $rows = [];
        foreach ($resp['rows'] ?? [] as $r) {
            $vals = array_merge(
                array_map(fn($v) => $v['value'], $r['dimensionValues'] ?? []),
                array_map(fn($v) => $v['value'], $r['metricValues'] ?? [])
            );
            $rows[] = array_combine($headers, $vals);
        }

        return ['headers' => $headers, 'rows' => $rows, 'row_count' => count($rows)];
    }

    protected function makeChart(array $args): array
    {
        $type = $args['type'] ?? 'bar';
        $labels = $args['labels'] ?? [];
        $datasets = $args['datasets'] ?? [];
        $title = $args['title'] ?? '';

        if (!in_array($type, ['bar', 'line', 'pie', 'doughnut'])) {
            return ['error' => 'Unsupported chart type. Use bar|line|pie|doughnut.'];
        }
        if (!$labels || !$datasets) {
            return ['error' => 'labels and datasets required.'];
        }

        $config = [
            'type' => $type,
            'data' => ['labels' => $labels, 'datasets' => $datasets],
            'options' => [
                'title' => ['display' => (bool)$title, 'text' => $title],
                'plugins' => ['legend' => ['display' => count($datasets) > 1]],
            ],
        ];

        $url = 'https://quickchart.io/chart?w=600&h=320&bkg=white&c=' . rawurlencode(json_encode($config));
        $imgTag = '<img src="' . $url . '" alt="' . e($title ?: 'chart') . '" style="max-width:100%;height:auto;margin:1em 0">';

        return [
            'chart_url' => $url,
            'embed_html' => $imgTag,
            'instruction' => 'Paste the embed_html into your final HTML report where the chart should appear.',
        ];
    }

    protected function summarize(array $result): string
    {
        if (isset($result['error'])) return 'error: ' . $result['error'];
        $count = $result['row_count'] ?? (isset($result['rows']) ? count($result['rows']) : 0);
        return "returned $count rows";
    }

    protected function systemPrompt(): string
    {
        $today = now()->toDateString();
        return <<<PROMPT
You are an analytics agent for a small business. Answer the user's question by calling tools (ga4_query, gsc_query) to fetch data from Google Analytics 4 and Google Search Console, then write an HTML report.

Today is {$today}. Interpret relative dates like "last 7 days", "last month" relative to today.

Rules:
- Use ga4_query for traffic, users, sessions, pages, sources, devices, countries, conversions.
- Use gsc_query for search queries, impressions, clicks, CTR, positions.
- You may call tools multiple times (e.g., current vs previous period for comparison).
- After gathering data, write a concise HTML report using <h2>, <p>, <ul>, <table>, <strong> only. NO markdown.
- Compute deltas yourself from actual numbers. Do not invent numbers.
- Be specific, concise, plain English. No fluff.
- End with 2-3 actionable recommendations when relevant.
- When a chart would clarify trends or comparisons, call make_chart and embed the returned embed_html in your report. Use charts sparingly (0-2 per report).

GA4 dimensions: date, pagePath, landingPage, sessionSource, sessionMedium, sessionSourceMedium, deviceCategory, country, city, browser, sessionDefaultChannelGroup, firstUserSource.
GA4 metrics: sessions, totalUsers, newUsers, screenPageViews, conversions, engagementRate, bounceRate, averageSessionDuration, eventCount.

GSC dimensions: query, page, device, country, date, searchAppearance.
GSC metrics (auto-returned): clicks, impressions, ctr, position.
PROMPT;
    }

    protected function toolDefs(): array
    {
        return [
            [
                'name' => 'ga4_query',
                'description' => 'Query Google Analytics 4. Returns rows of dimension+metric values.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'dimensions' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'GA4 dimension names.'],
                        'metrics' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'GA4 metric names. Required.'],
                        'date_start' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'date_end' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'order_by_metric' => ['type' => 'string', 'description' => 'Metric name to sort by.'],
                        'order_by_desc' => ['type' => 'boolean'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 100).'],
                    ],
                    'required' => ['metrics'],
                ],
            ],
            [
                'name' => 'gsc_query',
                'description' => 'Query Google Search Console. Returns clicks/impressions/ctr/position per dimension combination.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'dimensions' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'GSC dimensions.'],
                        'date_start' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'date_end' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 500).'],
                    ],
                ],
            ],
            [
                'name' => 'make_chart',
                'description' => 'Build a chart image URL (QuickChart.io). Returns embed_html to paste into the final report.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'description' => 'bar | line | pie | doughnut'],
                        'title' => ['type' => 'string'],
                        'labels' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'X-axis labels or slice names.'],
                        'datasets' => [
                            'type' => 'array',
                            'description' => 'Array of {label, data:[numbers]}.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'label' => ['type' => 'string'],
                                    'data' => ['type' => 'array', 'items' => ['type' => 'number']],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['type', 'labels', 'datasets'],
                ],
            ],
            [
                'name' => 'today_date',
                'description' => 'Get today\'s date in YYYY-MM-DD. Use if unsure about relative dates.',
                'parameters' => ['type' => 'object', 'properties' => (object)[]],
            ],
        ];
    }
}
