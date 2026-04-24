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
    protected const MAX_ITERATIONS = 12;

    /** @var 'gemini'|'groq' — Groq preferred (faster, higher free tier) */
    protected string $backend;

    protected function pickBackend(): string
    {
        // Gemini first — Groq free tier TPM (12k/min) is too small for agent workflows
        // with multiple tool calls. Fall back to Groq only if Gemini has no key.
        $gemini = config('services.gemini.key');
        if ($gemini) return 'gemini';
        return (new GroqService())->available() ? 'groq' : 'gemini';
    }

    public function __construct(public Connection $conn) {}

    public function run(string $userPrompt): array
    {
        $this->backend = $this->pickBackend();
        $google = new GoogleService($this->conn);
        $toolCalls = [];
        $nudged = false;
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

                // Agent wants to ask the user a clarifying question — bail out early.
                if ($fn === 'ask_user') {
                    return [
                        'ask_user' => true,
                        'question' => trim($args['question'] ?? 'Can you clarify?'),
                        'original_prompt' => $userPrompt,
                        'tool_calls' => $toolCalls,
                        'iterations' => $i + 1,
                        'backend' => $this->backend,
                    ];
                }

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
                // Nudge the model once — sometimes it stalls after tool calls.
                if (!empty($toolCalls) && empty($nudged)) {
                    $nudged = true;
                    $nudge = "Now write the final HTML report using ONLY the data from the tool calls above. Use <h2>, <p>, <table>, <strong>. Start with a 1-sentence summary. Include a table with the top 5 pages showing Previous clicks, Current clicks, and Delta. No markdown. No code fences. Do not call any more tools.";
                    $geminiHistory[] = ['role' => 'user', 'parts' => [['text' => $nudge]]];
                    $groqHistory[] = ['role' => 'user', 'content' => $nudge];
                    continue;
                }

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

        // Detect Groq rate-limit / error → switch to Gemini for the rest of the run.
        if (isset($resp['error']) || (isset($resp['choices'][0]['message']['content']) && str_contains((string)$resp['choices'][0]['message']['content'], 'rate_limit_exceeded'))) {
            Log::warning('Groq rate-limit/error — switching to Gemini', ['resp_snippet' => substr(json_encode($resp), 0, 300)]);
            $this->backend = 'gemini';
            return ['switched' => true];
        }

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
            'rowLimit' => min((int)($args['limit'] ?? 25), 50),
        ];

        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site) . '/searchAnalytics/query';
        $resp = Http::withToken($google->publicToken())->post($url, $body)->json();

        // Compact rows: flatten keys[] + round numbers to shrink LLM context.
        $dims = $body['dimensions'];
        $rows = [];
        foreach (array_slice($resp['rows'] ?? [], 0, $body['rowLimit']) as $r) {
            $row = [];
            foreach ($dims as $i => $d) {
                $row[$d] = $r['keys'][$i] ?? null;
            }
            $row['clicks'] = (int)($r['clicks'] ?? 0);
            $row['impressions'] = (int)($r['impressions'] ?? 0);
            $row['ctr'] = round(($r['ctr'] ?? 0) * 100, 2);
            $row['position'] = round($r['position'] ?? 0, 1);
            $rows[] = $row;
        }

        return ['rows' => $rows, 'row_count' => count($rows)];
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
        $ga4 = $this->conn->ga4_property_id ? "YES (property {$this->conn->ga4_property_id})" : 'NO';
        $gsc = $this->conn->gsc_site_url ? "YES (site {$this->conn->gsc_site_url})" : 'NO';
        return <<<PROMPT
You are an analytics agent for a small business. Answer the user's question by calling tools (ga4_query, gsc_query) to fetch data from Google Analytics 4 and Google Search Console, then write an HTML report.

Today is {$today}. Interpret relative dates like "last 7 days", "last 28 days", "last month" relative to today. Always resolve to concrete YYYY-MM-DD dates before calling tools.

# Connected data sources
- GA4 available: {$ga4}
- Search Console available: {$gsc}

Use only sources that are available. If the question needs a source that is NOT available, say so once and proceed with what you have — do NOT call ask_user about unavailable sources.

# Which source to use (do not ask the user this)
- "clicks", "impressions", "CTR", "position", "queries", "keywords", "ranking", "SERP" → **gsc_query** (ALWAYS GSC)
- "sessions", "users", "visitors", "bounce", "engagement", "conversions", "revenue", "source/medium", "channel" → **ga4_query** (ALWAYS GA4)
- "pages" or "traffic" → prefer whichever source is available. If both, GA4 for traffic; GSC if question involves search clicks/impressions.

Default time window if user doesn't specify: **last 28 days vs previous 28 days** for comparisons, otherwise **last 28 days** for single-period queries.

# Tool usage rules

- Use ga4_query for traffic, users, sessions, pages, sources, devices, countries, conversions.
- Use gsc_query for search queries, impressions, clicks, CTR, positions.
- You MUST always call at least one tool before writing the final report. Never guess data.
- You can (and should) call tools multiple times — especially for comparison questions.
- Call ask_user ONLY as a last resort, when the question has multiple genuinely plausible readings you cannot break with defaults. Examples of when NOT to ask: missing time window (use 28 days), which source to use (use the rules above), which property (use whatever is connected). Examples of when asking is OK: user says "compare my two main pages" without saying which two, or asks something outside GA4/GSC scope with no obvious substitute.

# Answering patterns (follow these, don't skip)

## Pattern: "biggest improvement / decline / change in X" over a period
The user is asking for a DELTA between current period and previous period.
Steps:
1. Resolve current period: e.g. "last 28 days" = today minus 27 → today.
2. Resolve previous period: the same length immediately before current. For 28 days: today-55 → today-28.
3. Call gsc_query TWICE (or ga4_query twice), once per period, with dimension=page (or query) and limit 30 (KEEP IT SMALL to fit in context).
4. Join the results yourself: for each page/query appearing in current, find its value in previous. Compute delta = current - previous. Missing = treat previous as 0.
5. Sort by delta descending (or ascending for decline) and take top N.
6. Present in a table: Page | Previous clicks | Current clicks | Delta.

Example concrete dates for "last 28 days vs previous 28 days" on a day like 2026-04-24:
- Current:  2026-03-28 → 2026-04-24
- Previous: 2026-02-29 → 2026-03-27

## Pattern: "top N pages by clicks/traffic/etc"
Single query. GSC: dimensions=[page], sort by clicks desc, limit N. GA4: dimensions=[pagePath], metrics=[screenPageViews or sessions], order_by_metric, limit N.

## Pattern: "where is my traffic coming from"
GA4 dimensions=[sessionSourceMedium] or [sessionDefaultChannelGroup], metrics=[sessions, totalUsers], limit 20.

# After fetching data

- Write a concise HTML report using <h2>, <p>, <ul>, <ol>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong> only. NO markdown, NO code fences.
- For delta tables: show Previous, Current, Delta (and % if clear). Use the actual numbers from tool output — never invent.
- Start with a 1-2 sentence summary of what you found.
- End with 2-3 actionable recommendations when the data warrants it.
- When a chart clarifies a trend, call make_chart and embed the returned embed_html. Max 2 charts.

# Dimension + metric reference

GA4 dimensions: date, pagePath, landingPage, sessionSource, sessionMedium, sessionSourceMedium, deviceCategory, country, city, browser, sessionDefaultChannelGroup, firstUserSource.
GA4 metrics: sessions, totalUsers, newUsers, screenPageViews, conversions, engagementRate, bounceRate, averageSessionDuration, eventCount.

GSC dimensions: query, page, device, country, date, searchAppearance.
GSC metrics (auto-returned): clicks, impressions, ctr, position.

# What you cannot do

You can ONLY access GA4 and Search Console data. You CANNOT access PageSpeed / Core Web Vitals, backlinks, Google Ads, heatmaps, social media analytics, or revenue unless tracked in GA4 conversions. If the user asks for those, explain briefly and suggest a rephrased question using data you CAN fetch.
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
            [
                'name' => 'ask_user',
                'description' => 'Ask the user a short clarifying question when the request is genuinely ambiguous (missing time window, unclear metric, multiple plausible interpretations). Use sparingly — only if you cannot reasonably guess the intent. Do NOT use for simple/obvious questions; just fetch the data.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'question' => ['type' => 'string', 'description' => 'A single clear question in plain English, max 200 chars. Offer 2-3 concrete options when possible.'],
                    ],
                    'required' => ['question'],
                ],
            ],
        ];
    }
}
