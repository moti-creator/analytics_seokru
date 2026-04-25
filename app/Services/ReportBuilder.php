<?php

namespace App\Services;

use App\Models\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReportBuilder
{
    public const TYPES = [
        'keyword_rankings' => ['title' => 'Keyword Rankings Pivot (Web)', 'needs' => ['gsc']],
        'keyword_rankings_news' => ['title' => 'Keyword Rankings Pivot (News)', 'needs' => ['gsc']],
        'content_decay' => ['title' => 'Content Decay Report', 'needs' => ['ga4']],
        'striking_distance' => ['title' => 'Striking-Distance Keywords', 'needs' => ['gsc']],
        'conversion_leak' => ['title' => 'Conversion Leak Finder', 'needs' => ['ga4']],
        'anomaly' => ['title' => 'Weekly Anomaly Scan', 'needs' => ['ga4','gsc']],
        'brand_split' => ['title' => 'Brand vs Non-Brand Split', 'needs' => ['gsc']],
        // Cross-platform (GA4 + GSC join) — the reports GA4's AI can't do
        'silent_winners' => ['title' => 'Silent Winners (High Impressions, Low Engagement)', 'needs' => ['ga4','gsc']],
        'converting_queries' => ['title' => 'Converting Queries You\'re Losing', 'needs' => ['ga4','gsc']],
        'cannibalization' => ['title' => 'Keyword Cannibalization Detector', 'needs' => ['ga4','gsc']],
        'brand_rescue' => ['title' => 'Brand Rescue vs Real Growth', 'needs' => ['ga4','gsc']],
        'llm_traffic' => ['title' => 'LLM Traffic — Visitors from ChatGPT, Perplexity, Claude & Co.', 'needs' => ['ga4']],
    ];

    /** Types that render their own narrative and skip the LLM pipeline. */
    protected const PREBUILT_TYPES = ['keyword_rankings', 'keyword_rankings_news'];

    public function __construct(public Connection $conn) {}

    /**
     * Check which data sources are available vs needed.
     */
    protected function sourceCheck(string $type): array
    {
        $needs = self::TYPES[$type]['needs'] ?? [];
        $hasGa4 = !empty($this->conn->ga4_property_id);
        $hasGsc = !empty($this->conn->gsc_site_url);
        $missing = [];
        if (in_array('ga4', $needs) && !$hasGa4) $missing[] = 'GA4';
        if (in_array('gsc', $needs) && !$hasGsc) $missing[] = 'Search Console';
        return ['missing' => $missing, 'partial' => count($missing) > 0 && count($missing) < count($needs)];
    }

    public function build(string $type): array
    {
        // Cache key: same user + same report type + same day = same result.
        // Saves Gemini/Groq calls. Cache for 12 hours.
        $cacheKey = "report:{$this->conn->id}:{$type}:" . now()->toDateString();

        return Cache::remember($cacheKey, 60 * 60 * 12, function () use ($type) {
            $check = $this->sourceCheck($type);
            $g = new GoogleService($this->conn);

            // Prebuilt reports skip the LLM — they return their own narrative.
            if (in_array($type, self::PREBUILT_TYPES, true)) {
                return $this->buildPrebuilt($type, $g);
            }

            $data = match($type) {
                'content_decay' => $this->contentDecay($g),
                'striking_distance' => $this->strikingDistance($g),
                'conversion_leak' => $this->conversionLeak($g),
                'anomaly' => $this->anomaly($g),
                'brand_split' => $this->brandSplit($g),
                'silent_winners' => $this->silentWinners($g),
                'converting_queries' => $this->convertingQueries($g),
                'cannibalization' => $this->cannibalization($g),
                'brand_rescue' => $this->brandRescue($g),
                'llm_traffic' => $this->llmTraffic($g),
            };
            // Add source availability info so the LLM knows what data it has
            if ($check['missing']) {
                $data['_note'] = 'Missing data source(s): ' . implode(', ', $check['missing']) . '. Only analyze data that is present. Do not reference missing data.';
            }

            // Generate charts and inject into data for LLM to embed
            $this->injectCharts($type, $data);

            $narrative = (new GeminiService())->raw($this->prompt($type, $data));
            return [
                'type' => $type,
                'title' => self::TYPES[$type]['title'],
                'metrics' => $data,
                'narrative' => $narrative,
            ];
        });
    }

    protected function contentDecay(GoogleService $g): array
    {
        $pid = $this->conn->ga4_property_id;
        $curEnd = now()->subDay()->toDateString();
        $curStart = now()->subDays(90)->toDateString();
        $prevEnd = now()->subDays(91)->toDateString();
        $prevStart = now()->subDays(180)->toDateString();

        $cur = $this->ga4LandingPages($g, $pid, $curStart, $curEnd);
        $prev = $this->ga4LandingPages($g, $pid, $prevStart, $prevEnd);

        $prevMap = collect($prev)->keyBy('page');
        $decayed = collect($cur)->map(function ($r) use ($prevMap) {
            $p = $prevMap[$r['page']]['sessions'] ?? 0;
            $delta = $r['sessions'] - $p;
            $pct = $p > 0 ? round(($delta / $p) * 100, 1) : null;
            return array_merge($r, ['prev_sessions' => $p, 'delta' => $delta, 'pct' => $pct]);
        })->filter(fn($r) => $r['pct'] !== null && $r['pct'] < -15 && $r['prev_sessions'] >= 50)
          ->sortBy('pct')->take(15)->values()->all();

        return ['period' => "$curStart to $curEnd vs $prevStart to $prevEnd", 'decayed_pages' => $decayed];
    }

    protected function strikingDistance(GoogleService $g): array
    {
        $end = now()->subDays(3)->toDateString();
        $start = now()->subDays(31)->toDateString();
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($this->conn->gsc_site_url) . '/searchAnalytics/query';

        $rows = Http::withToken($g->publicToken())
            ->post($url, ['startDate'=>$start,'endDate'=>$end,'dimensions'=>['query'],'rowLimit'=>1000])
            ->json('rows', []);

        $opps = collect($rows)->map(fn($r) => [
            'query' => $r['keys'][0], 'clicks' => $r['clicks'], 'impressions' => $r['impressions'],
            'ctr' => round($r['ctr'] * 100, 2), 'position' => round($r['position'], 1),
        ])->filter(fn($r) => $r['position'] >= 4 && $r['position'] <= 20 && $r['impressions'] >= 50)
          ->sortByDesc('impressions')->take(25)->values()->all();

        return ['period' => "$start to $end", 'opportunities' => $opps];
    }

    protected function conversionLeak(GoogleService $g): array
    {
        $pid = $this->conn->ga4_property_id;
        $end = now()->subDay()->toDateString();
        $start = now()->subDays(28)->toDateString();

        $resp = Http::withToken($g->publicToken())->post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$pid}:runReport",
            [
                'dateRanges' => [['startDate'=>$start,'endDate'=>$end]],
                'dimensions' => [['name'=>'landingPage']],
                'metrics' => [['name'=>'sessions'],['name'=>'conversions'],['name'=>'engagementRate']],
                'orderBys' => [['metric'=>['metricName'=>'sessions'],'desc'=>true]],
                'limit' => 100,
            ]
        )->json();

        $rows = collect($resp['rows'] ?? [])->map(fn($r) => [
            'page' => $r['dimensionValues'][0]['value'],
            'sessions' => (int)$r['metricValues'][0]['value'],
            'conversions' => (float)$r['metricValues'][1]['value'],
            'engagement' => round((float)$r['metricValues'][2]['value'] * 100, 1),
        ]);
        $median = $rows->median('sessions') ?: 0;
        $leaks = $rows->filter(fn($r) => $r['sessions'] >= max(50, $median) && $r['conversions'] < 1)
            ->sortByDesc('sessions')->take(15)->values()->all();

        return ['period' => "$start to $end", 'leaks' => $leaks];
    }

    protected function anomaly(GoogleService $g): array
    {
        $end = now()->subDay()->toDateString();
        $start = now()->subDays(7)->toDateString();
        $prevEnd = now()->subDays(8)->toDateString();
        $prevStart = now()->subDays(14)->toDateString();

        $hasGa4 = !empty($this->conn->ga4_property_id);
        $hasGsc = !empty($this->conn->gsc_site_url);
        $ga = $hasGa4 ? $g->fetchGa4($this->conn->ga4_property_id, $start, $end) : [];
        $gaPrev = $hasGa4 ? $g->fetchGa4($this->conn->ga4_property_id, $prevStart, $prevEnd) : [];
        $gsc = $hasGsc ? $g->fetchGsc($this->conn->gsc_site_url, $start, $end) : [];
        $gscPrev = $hasGsc ? $g->fetchGsc($this->conn->gsc_site_url, $prevStart, $prevEnd) : [];

        // --- Pre-compute GA4 totals with deltas (so LLM can't invent math) ---
        // Metric order matches GoogleService::fetchGa4(): sessions, totalUsers, screenPageViews, conversions, engagementRate
        $ga4Metrics = ['sessions', 'totalUsers', 'screenPageViews', 'conversions', 'engagementRate'];
        $curVals = $this->extractMetricRow($ga['totals'] ?? [], $ga4Metrics);
        $prevVals = $this->extractMetricRow($gaPrev['totals'] ?? [], $ga4Metrics);

        $ga4Deltas = [];
        foreach ($ga4Metrics as $m) {
            $ga4Deltas[$m] = $this->delta($curVals[$m] ?? 0, $prevVals[$m] ?? 0, $m === 'engagementRate' ? 4 : 0);
        }

        // --- GA4 top sources delta (by session count per source) ---
        $curSources = $this->indexRowsBy($ga['topSources']['rows'] ?? [], 'sessions');
        $prevSources = $this->indexRowsBy($gaPrev['topSources']['rows'] ?? [], 'sessions');
        $sourceDeltas = [];
        foreach ($curSources as $src => $curSess) {
            $prevSess = $prevSources[$src] ?? 0;
            $sourceDeltas[] = [
                'source' => $src,
                'current' => $curSess,
                'previous' => $prevSess,
                'delta' => $curSess - $prevSess,
                'pct_change' => $this->pct($curSess, $prevSess),
            ];
        }
        usort($sourceDeltas, fn($a,$b) => abs($b['pct_change'] ?? 0) <=> abs($a['pct_change'] ?? 0));

        // --- GSC totals deltas ---
        $gscCur = $gsc['totals']['rows'][0] ?? [];
        $gscPrv = $gscPrev['totals']['rows'][0] ?? [];
        $gscDeltas = [
            'clicks' => $this->delta($gscCur['clicks'] ?? 0, $gscPrv['clicks'] ?? 0),
            'impressions' => $this->delta($gscCur['impressions'] ?? 0, $gscPrv['impressions'] ?? 0),
            'ctr' => $this->delta(($gscCur['ctr'] ?? 0) * 100, ($gscPrv['ctr'] ?? 0) * 100, 2),
            'position' => $this->delta($gscCur['position'] ?? 0, $gscPrv['position'] ?? 0, 1),
        ];

        // Flag metrics that moved >20% (LLM will call these out)
        $bigSwings = [];
        foreach ($ga4Deltas as $m => $d) {
            if ($d['pct_change'] !== null && abs($d['pct_change']) >= 20) {
                $bigSwings[] = ['metric' => "ga4.$m"] + $d;
            }
        }
        foreach ($gscDeltas as $m => $d) {
            if ($d['pct_change'] !== null && abs($d['pct_change']) >= 20) {
                $bigSwings[] = ['metric' => "gsc.$m"] + $d;
            }
        }

        return [
            'period_current' => "$start to $end",
            'period_previous' => "$prevStart to $prevEnd",
            'ga4_deltas' => $ga4Deltas,
            'gsc_deltas' => $gscDeltas,
            'source_deltas' => array_slice($sourceDeltas, 0, 10),
            'big_swings' => $bigSwings,
        ];
    }

    protected function brandSplit(GoogleService $g): array
    {
        $end = now()->subDays(3)->toDateString();
        $start = now()->subDays(31)->toDateString();
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($this->conn->gsc_site_url) . '/searchAnalytics/query';

        $rows = Http::withToken($g->publicToken())
            ->post($url, ['startDate'=>$start,'endDate'=>$end,'dimensions'=>['query'],'rowLimit'=>5000])
            ->json('rows', []);

        $host = parse_url($this->conn->gsc_site_url, PHP_URL_HOST) ?: $this->conn->gsc_site_url;
        $brand = preg_replace('/^(www\.|sc-domain:)/', '', $host);
        $brand = strtolower(explode('.', $brand)[0]);

        $brandClicks = 0; $nonBrandClicks = 0; $brandImp = 0; $nonBrandImp = 0;
        $brandQs = []; $nonBrandQs = [];

        foreach ($rows as $r) {
            $q = strtolower($r['keys'][0]);
            $isBrand = str_contains($q, $brand);
            if ($isBrand) { $brandClicks += $r['clicks']; $brandImp += $r['impressions']; $brandQs[] = $r; }
            else { $nonBrandClicks += $r['clicks']; $nonBrandImp += $r['impressions']; $nonBrandQs[] = $r; }
        }

        usort($brandQs, fn($a,$b) => $b['clicks'] <=> $a['clicks']);
        usort($nonBrandQs, fn($a,$b) => $b['clicks'] <=> $a['clicks']);

        return [
            'period' => "$start to $end",
            'brand_term' => $brand,
            'brand' => ['clicks' => $brandClicks, 'impressions' => $brandImp, 'top_queries' => array_slice($brandQs, 0, 10)],
            'non_brand' => ['clicks' => $nonBrandClicks, 'impressions' => $nonBrandImp, 'top_queries' => array_slice($nonBrandQs, 0, 10)],
        ];
    }

    /**
     * Silent Winners: queries with high impressions + poor CTR, where GA4 shows
     * the landing page has poor engagement. Title/intent mismatch candidates.
     */
    protected function silentWinners(GoogleService $g): array
    {
        $end = now()->subDays(3)->toDateString();
        $start = now()->subDays(28)->toDateString();
        $site = $this->conn->gsc_site_url;

        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site) . '/searchAnalytics/query';
        $rows = Http::withToken($g->publicToken())
            ->post($url, ['startDate'=>$start,'endDate'=>$end,'dimensions'=>['query','page'],'rowLimit'=>5000])
            ->json('rows', []);

        $candidates = collect($rows)->filter(fn($r) =>
            $r['impressions'] >= 500 && $r['position'] <= 10 && $r['ctr'] < 0.02
        )->map(fn($r) => [
            'query' => $r['keys'][0],
            'page' => $r['keys'][1],
            'impressions' => (int)$r['impressions'],
            'clicks' => (int)$r['clicks'],
            'ctr' => round($r['ctr'] * 100, 2),
            'position' => round($r['position'], 1),
        ])->sortByDesc('impressions')->take(25)->values()->all();

        $pagePaths = array_unique(array_map(fn($c) => $this->pathOnly($c['page']), $candidates));
        $engagement = $this->ga4EngagementForPages($g, $pagePaths, $start, $end);

        foreach ($candidates as &$c) {
            $p = $this->pathOnly($c['page']);
            $e = $engagement[$p] ?? null;
            $c['ga4_sessions'] = $e['sessions'] ?? null;
            $c['ga4_engagement_rate'] = $e['engagement_rate'] ?? null;
            $c['ga4_bounce_rate'] = $e['bounce_rate'] ?? null;
        }

        return ['period' => "$start to $end", 'silent_winners' => $candidates];
    }

    /**
     * Converting Queries Slipping: GA4 top revenue/conversion pages, cross-ref GSC
     * for their top query + rank trend (current vs 90d ago).
     */
    protected function convertingQueries(GoogleService $g): array
    {
        $pid = $this->conn->ga4_property_id;
        $end = now()->subDay()->toDateString();
        $start = now()->subDays(28)->toDateString();

        $resp = Http::withToken($g->publicToken())->post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$pid}:runReport",
            [
                'dateRanges' => [['startDate'=>$start,'endDate'=>$end]],
                'dimensions' => [['name'=>'landingPage']],
                'metrics' => [['name'=>'conversions'],['name'=>'sessions']],
                'orderBys' => [['metric'=>['metricName'=>'conversions'],'desc'=>true]],
                'limit' => 20,
            ]
        )->json();

        $topPages = collect($resp['rows'] ?? [])->map(fn($r) => [
            'page' => $r['dimensionValues'][0]['value'],
            'conversions' => (float)$r['metricValues'][0]['value'],
            'sessions' => (int)$r['metricValues'][1]['value'],
        ])->filter(fn($r) => $r['conversions'] > 0)->values()->all();

        // GSC current + 90d-ago snapshots, dims=query+page
        $site = $this->conn->gsc_site_url;
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site) . '/searchAnalytics/query';
        $curRows = Http::withToken($g->publicToken())->post($url, [
            'startDate' => now()->subDays(31)->toDateString(),
            'endDate' => now()->subDays(3)->toDateString(),
            'dimensions' => ['query','page'], 'rowLimit' => 10000,
        ])->json('rows', []);
        $oldRows = Http::withToken($g->publicToken())->post($url, [
            'startDate' => now()->subDays(121)->toDateString(),
            'endDate' => now()->subDays(93)->toDateString(),
            'dimensions' => ['query','page'], 'rowLimit' => 10000,
        ])->json('rows', []);

        $indexByPage = function ($rows) {
            $map = [];
            foreach ($rows as $r) {
                $p = $this->pathOnly($r['keys'][1]);
                if (!isset($map[$p]) || $r['clicks'] > $map[$p]['clicks']) {
                    $map[$p] = ['query' => $r['keys'][0], 'clicks' => $r['clicks'], 'position' => $r['position']];
                }
            }
            return $map;
        };
        $curMap = $indexByPage($curRows);
        $oldMap = $indexByPage($oldRows);

        foreach ($topPages as &$p) {
            $path = $this->pathOnly($p['page']);
            $cur = $curMap[$path] ?? null;
            $old = $oldMap[$path] ?? null;
            $p['top_query'] = $cur['query'] ?? null;
            $p['position_now'] = $cur ? round($cur['position'], 1) : null;
            $p['position_90d_ago'] = $old ? round($old['position'], 1) : null;
            $p['position_change'] = ($cur && $old) ? round($cur['position'] - $old['position'], 1) : null;
        }

        usort($topPages, fn($a,$b) => ($b['position_change'] ?? -99) <=> ($a['position_change'] ?? -99));
        return ['period' => "$start to $end vs 90d prior", 'pages' => $topPages];
    }

    /**
     * Cannibalization: queries where 2+ of your URLs rank within top 20.
     * Cross-ref GA4 to see which URL actually converts.
     */
    protected function cannibalization(GoogleService $g): array
    {
        $end = now()->subDays(3)->toDateString();
        $start = now()->subDays(28)->toDateString();
        $site = $this->conn->gsc_site_url;

        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site) . '/searchAnalytics/query';
        $rows = Http::withToken($g->publicToken())
            ->post($url, ['startDate'=>$start,'endDate'=>$end,'dimensions'=>['query','page'],'rowLimit'=>10000])
            ->json('rows', []);

        $byQuery = [];
        foreach ($rows as $r) {
            if ($r['position'] > 20 || $r['impressions'] < 100) continue;
            $byQuery[$r['keys'][0]][] = [
                'page' => $r['keys'][1],
                'clicks' => (int)$r['clicks'],
                'impressions' => (int)$r['impressions'],
                'position' => round($r['position'], 1),
            ];
        }
        $conflicts = array_filter($byQuery, fn($urls) => count($urls) >= 2);
        uasort($conflicts, fn($a,$b) =>
            array_sum(array_column($b, 'impressions')) <=> array_sum(array_column($a, 'impressions'))
        );
        $conflicts = array_slice($conflicts, 0, 15, true);

        // GA4 conversions per involved page
        $allPages = [];
        foreach ($conflicts as $urls) foreach ($urls as $u) $allPages[] = $this->pathOnly($u['page']);
        $conv = $this->ga4ConversionsForPages($g, array_unique($allPages), $start, $end);

        $out = [];
        foreach ($conflicts as $query => $urls) {
            foreach ($urls as &$u) {
                $p = $this->pathOnly($u['page']);
                $u['ga4_conversions'] = $conv[$p]['conversions'] ?? 0;
                $u['ga4_sessions'] = $conv[$p]['sessions'] ?? 0;
            }
            $out[] = ['query' => $query, 'urls' => $urls];
        }

        return ['period' => "$start to $end", 'conflicts' => $out];
    }

    /**
     * Brand Rescue: split sessions/conversions attributable to brand vs non-brand
     * organic landing pages. Current vs previous period — detects non-brand decay
     * masked by brand stability.
     */
    protected function brandRescue(GoogleService $g): array
    {
        $end = now()->subDays(3)->toDateString();
        $start = now()->subDays(28)->toDateString();
        $prevEnd = now()->subDays(31)->toDateString();
        $prevStart = now()->subDays(59)->toDateString();

        $host = parse_url($this->conn->gsc_site_url, PHP_URL_HOST) ?: $this->conn->gsc_site_url;
        $brand = preg_replace('/^(www\.|sc-domain:)/', '', $host);
        $brand = strtolower(explode('.', $brand)[0]);

        $classify = function ($rows) use ($brand) {
            $brandPages = []; $nonBrandPages = [];
            $brandClicks = 0; $nonBrandClicks = 0;
            foreach ($rows as $r) {
                $q = strtolower($r['keys'][0]);
                $p = $this->pathOnly($r['keys'][1]);
                if (str_contains($q, $brand)) {
                    $brandPages[$p] = ($brandPages[$p] ?? 0) + $r['clicks'];
                    $brandClicks += $r['clicks'];
                } else {
                    $nonBrandPages[$p] = ($nonBrandPages[$p] ?? 0) + $r['clicks'];
                    $nonBrandClicks += $r['clicks'];
                }
            }
            return compact('brandPages','nonBrandPages','brandClicks','nonBrandClicks');
        };

        $site = $this->conn->gsc_site_url;
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site) . '/searchAnalytics/query';
        $curRows = Http::withToken($g->publicToken())->post($url, [
            'startDate'=>$start,'endDate'=>$end,'dimensions'=>['query','page'],'rowLimit'=>10000,
        ])->json('rows', []);
        $prevRows = Http::withToken($g->publicToken())->post($url, [
            'startDate'=>$prevStart,'endDate'=>$prevEnd,'dimensions'=>['query','page'],'rowLimit'=>10000,
        ])->json('rows', []);

        $cur = $classify($curRows);
        $prev = $classify($prevRows);

        // GA4 conversions on brand-leading vs non-brand-leading pages
        $conv = $this->ga4ConversionsForPages($g,
            array_unique(array_merge(array_keys($cur['brandPages']), array_keys($cur['nonBrandPages']))),
            $start, $end
        );
        $brandConv = 0; $nonBrandConv = 0;
        foreach ($cur['brandPages'] as $p => $_) $brandConv += $conv[$p]['conversions'] ?? 0;
        foreach ($cur['nonBrandPages'] as $p => $_) $nonBrandConv += $conv[$p]['conversions'] ?? 0;

        return [
            'brand_term' => $brand,
            'period_current' => "$start to $end",
            'period_previous' => "$prevStart to $prevEnd",
            'brand' => [
                'clicks_current' => $cur['brandClicks'],
                'clicks_previous' => $prev['brandClicks'],
                'clicks_delta' => $cur['brandClicks'] - $prev['brandClicks'],
                'clicks_pct_change' => $this->pct($cur['brandClicks'], $prev['brandClicks']),
                'conversions_current' => round($brandConv, 2),
            ],
            'non_brand' => [
                'clicks_current' => $cur['nonBrandClicks'],
                'clicks_previous' => $prev['nonBrandClicks'],
                'clicks_delta' => $cur['nonBrandClicks'] - $prev['nonBrandClicks'],
                'clicks_pct_change' => $this->pct($cur['nonBrandClicks'], $prev['nonBrandClicks']),
                'conversions_current' => round($nonBrandConv, 2),
            ],
            'verdict' => $this->brandVerdict(
                $this->pct($cur['brandClicks'], $prev['brandClicks']),
                $this->pct($cur['nonBrandClicks'], $prev['nonBrandClicks'])
            ),
        ];
    }

    protected function brandVerdict(?float $brandPct, ?float $nonBrandPct): string
    {
        if ($brandPct === null || $nonBrandPct === null) return 'insufficient_data';
        if ($nonBrandPct < -5 && $brandPct >= -5) return 'non_brand_decaying_brand_masking';
        if ($nonBrandPct >= 10 && $brandPct >= 0) return 'genuine_growth';
        if ($brandPct < -10) return 'brand_decay_reputation_check';
        if ($nonBrandPct >= 0 && $brandPct >= 0) return 'stable_both';
        return 'mixed';
    }

    // --- Shared helpers ---

    protected function pathOnly(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return $path ?: $url;
    }

    protected function ga4EngagementForPages(GoogleService $g, array $pages, string $start, string $end): array
    {
        if (!$pages || !$this->conn->ga4_property_id) return [];
        $resp = Http::withToken($g->publicToken())->post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$this->conn->ga4_property_id}:runReport",
            [
                'dateRanges' => [['startDate'=>$start,'endDate'=>$end]],
                'dimensions' => [['name'=>'landingPage']],
                'metrics' => [['name'=>'sessions'],['name'=>'engagementRate'],['name'=>'bounceRate']],
                'limit' => 1000,
            ]
        )->json();

        $out = [];
        foreach ($resp['rows'] ?? [] as $r) {
            $p = $r['dimensionValues'][0]['value'];
            if (!in_array($p, $pages)) continue;
            $out[$p] = [
                'sessions' => (int)$r['metricValues'][0]['value'],
                'engagement_rate' => round((float)$r['metricValues'][1]['value'] * 100, 1),
                'bounce_rate' => round((float)$r['metricValues'][2]['value'] * 100, 1),
            ];
        }
        return $out;
    }

    protected function ga4ConversionsForPages(GoogleService $g, array $pages, string $start, string $end): array
    {
        if (!$pages || !$this->conn->ga4_property_id) return [];
        $resp = Http::withToken($g->publicToken())->post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$this->conn->ga4_property_id}:runReport",
            [
                'dateRanges' => [['startDate'=>$start,'endDate'=>$end]],
                'dimensions' => [['name'=>'landingPage']],
                'metrics' => [['name'=>'sessions'],['name'=>'conversions']],
                'limit' => 1000,
            ]
        )->json();

        $out = [];
        foreach ($resp['rows'] ?? [] as $r) {
            $p = $r['dimensionValues'][0]['value'];
            $out[$p] = [
                'sessions' => (int)$r['metricValues'][0]['value'],
                'conversions' => (float)$r['metricValues'][1]['value'],
            ];
        }
        return $out;
    }

    /**
     * LLM Traffic Report — detect referrals from AI chatbots/search.
     * Pulls GA4 sessionSource × landingPage, filters known LLM domains,
     * computes split, top landing pages, conversion if available.
     */
    protected function llmTraffic(GoogleService $g): array
    {
        $pid = $this->conn->ga4_property_id;
        $curEnd = now()->subDay()->toDateString();
        $curStart = now()->subDays(90)->toDateString();
        $prevEnd = now()->subDays(91)->toDateString();
        $prevStart = now()->subDays(180)->toDateString();

        // Known LLM referrer host patterns. Match against sessionSource (GA4 source = host).
        $llmMap = [
            'ChatGPT' => ['chatgpt.com', 'chat.openai.com', 'openai.com'],
            'Perplexity' => ['perplexity.ai', 'www.perplexity.ai'],
            'Claude' => ['claude.ai', 'anthropic.com'],
            'Gemini' => ['gemini.google.com', 'bard.google.com'],
            'Copilot' => ['copilot.microsoft.com', 'edgeservices.bing.com'],
            'Meta AI' => ['meta.ai'],
            'Phind' => ['phind.com'],
            'You.com' => ['you.com'],
            'Poe' => ['poe.com'],
            'Character.AI' => ['character.ai', 'beta.character.ai'],
            'DuckDuckGo AI' => ['duckduckgo.com'],
            'Mistral' => ['chat.mistral.ai'],
            'Grok' => ['grok.com', 'x.ai'],
        ];
        $allHosts = array_merge(...array_values($llmMap));

        $cur = $this->ga4SourceLanding($g, $pid, $curStart, $curEnd);
        $prev = $this->ga4SourceLanding($g, $pid, $prevStart, $prevEnd);

        $matchLlm = function (string $source) use ($llmMap): ?string {
            $s = strtolower($source);
            foreach ($llmMap as $name => $hosts) {
                foreach ($hosts as $h) {
                    if ($s === $h || str_contains($s, $h)) return $name;
                }
            }
            return null;
        };

        // Aggregate current period
        $perLlm = []; $perLanding = []; $totalSessions = 0; $totalLlmSessions = 0;
        $totalUsers = 0; $totalLlmUsers = 0; $totalLlmConv = 0;
        foreach ($cur as $r) {
            $totalSessions += $r['sessions'];
            $totalUsers += $r['users'];
            $llm = $matchLlm($r['source']);
            if (!$llm) continue;
            $totalLlmSessions += $r['sessions'];
            $totalLlmUsers += $r['users'];
            $totalLlmConv += $r['conversions'];

            $perLlm[$llm] ??= ['llm' => $llm, 'sessions' => 0, 'users' => 0, 'conversions' => 0];
            $perLlm[$llm]['sessions'] += $r['sessions'];
            $perLlm[$llm]['users'] += $r['users'];
            $perLlm[$llm]['conversions'] += $r['conversions'];

            $key = $llm . '|' . $r['page'];
            $perLanding[$key] ??= ['llm' => $llm, 'page' => $r['page'], 'sessions' => 0, 'conversions' => 0];
            $perLanding[$key]['sessions'] += $r['sessions'];
            $perLanding[$key]['conversions'] += $r['conversions'];
        }

        // Previous period totals (for delta)
        $prevLlmSessions = 0;
        foreach ($prev as $r) {
            if ($matchLlm($r['source'])) $prevLlmSessions += $r['sessions'];
        }

        // Sort + trim
        usort($perLlm, fn($a, $b) => $b['sessions'] <=> $a['sessions']);
        $perLlm = array_values($perLlm);
        usort($perLanding, fn($a, $b) => $b['sessions'] <=> $a['sessions']);
        $topLanding = array_values(array_slice($perLanding, 0, 25));

        // GSC-side: query patterns suggestive of conversational/LLM-style searches (long, question-form)
        $gscSignals = [];
        if ($this->conn->gsc_site_url) {
            try {
                $gscUrl = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($this->conn->gsc_site_url) . '/searchAnalytics/query';
                $resp = Http::withToken($g->publicToken())->post($gscUrl, [
                    'startDate' => $curStart, 'endDate' => $curEnd,
                    'dimensions' => ['query'], 'rowLimit' => 1000,
                ])->json();
                $rows = $resp['rows'] ?? [];
                foreach ($rows as $row) {
                    $q = strtolower($row['keys'][0] ?? '');
                    if (!$q) continue;
                    $isLong = str_word_count($q) >= 6;
                    $isQuestion = preg_match('/^(what|how|why|when|where|who|can|should|is|are|do|does|will)\b/', $q) === 1;
                    if ($isLong || $isQuestion) {
                        $gscSignals[] = [
                            'query' => $q,
                            'clicks' => (int)($row['clicks'] ?? 0),
                            'impressions' => (int)($row['impressions'] ?? 0),
                            'position' => round($row['position'] ?? 0, 1),
                        ];
                    }
                }
                usort($gscSignals, fn($a, $b) => $b['impressions'] <=> $a['impressions']);
                $gscSignals = array_slice($gscSignals, 0, 20);
            } catch (\Throwable $e) {}
        }

        return [
            'period_current' => "$curStart to $curEnd",
            'period_previous' => "$prevStart to $prevEnd",
            'total_sessions_current' => $totalSessions,
            'total_users_current' => $totalUsers,
            'llm_totals' => [
                'sessions_current' => $totalLlmSessions,
                'sessions_previous' => $prevLlmSessions,
                'sessions_delta' => $totalLlmSessions - $prevLlmSessions,
                'sessions_pct_change' => $this->pct($totalLlmSessions, $prevLlmSessions),
                'users_current' => $totalLlmUsers,
                'conversions_current' => $totalLlmConv,
                'share_of_total_pct' => $totalSessions > 0
                    ? round(($totalLlmSessions / max(1, $totalSessions)) * 100, 2) : 0,
            ],
            'per_llm' => $perLlm,
            'top_landing_pages' => $topLanding,
            'gsc_conversational_query_signals' => $gscSignals,
            'note' => $totalLlmSessions === 0
                ? 'No detected LLM traffic in the last 90 days. Either AI tools have not yet driven referrals to this site, OR they pass referral data inconsistently. Bots from LLMs (e.g. GPTBot, ClaudeBot) crawl content but do not produce sessions in GA4 — only humans clicking links do.'
                : null,
        ];
    }

    /**
     * GA4 query: sessionSource × landingPage with sessions, users, conversions.
     */
    protected function ga4SourceLanding(GoogleService $g, string $pid, string $start, string $end): array
    {
        $resp = Http::withToken($g->publicToken())->post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$pid}:runReport",
            [
                'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
                'dimensions' => [['name' => 'sessionSource'], ['name' => 'landingPage']],
                'metrics' => [
                    ['name' => 'sessions'],
                    ['name' => 'totalUsers'],
                    ['name' => 'conversions'],
                ],
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => 1000,
            ]
        )->json();

        return collect($resp['rows'] ?? [])->map(fn ($r) => [
            'source' => $r['dimensionValues'][0]['value'] ?? '',
            'page' => $r['dimensionValues'][1]['value'] ?? '',
            'sessions' => (int) ($r['metricValues'][0]['value'] ?? 0),
            'users' => (int) ($r['metricValues'][1]['value'] ?? 0),
            'conversions' => (int) ($r['metricValues'][2]['value'] ?? 0),
        ])->all();
    }

    protected function ga4LandingPages(GoogleService $g, string $pid, string $start, string $end): array
    {
        $resp = Http::withToken($g->publicToken())->post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$pid}:runReport",
            [
                'dateRanges' => [['startDate'=>$start,'endDate'=>$end]],
                'dimensions' => [['name'=>'landingPage']],
                'metrics' => [['name'=>'sessions']],
                'orderBys' => [['metric'=>['metricName'=>'sessions'],'desc'=>true]],
                'limit' => 500,
            ]
        )->json();

        return collect($resp['rows'] ?? [])->map(fn($r) => [
            'page' => $r['dimensionValues'][0]['value'],
            'sessions' => (int)$r['metricValues'][0]['value'],
        ])->all();
    }

    // --- Math helpers: pre-compute deltas server-side so LLM cannot hallucinate numbers ---

    protected function pct(float $current, float $previous): ?float
    {
        if ($previous == 0) return null;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function delta(float $current, float $previous, int $precision = 0): array
    {
        return [
            'current' => round($current, $precision),
            'previous' => round($previous, $precision),
            'delta' => round($current - $previous, $precision),
            'pct_change' => $this->pct($current, $previous),
        ];
    }

    /**
     * Pull named metrics from a GA4 runReport totals response row.
     */
    protected function extractMetricRow(array $resp, array $metricNames): array
    {
        $headers = array_map(fn($h) => $h['name'] ?? '', $resp['metricHeaders'] ?? []);
        $values = $resp['totals'][0]['metricValues'] ?? $resp['rows'][0]['metricValues'] ?? [];
        $out = [];
        foreach ($metricNames as $name) {
            $idx = array_search($name, $headers);
            $out[$name] = $idx !== false ? (float)($values[$idx]['value'] ?? 0) : 0;
        }
        return $out;
    }

    protected function indexRowsBy(array $rows, string $metric): array
    {
        $out = [];
        foreach ($rows as $r) {
            $key = $r['dimensionValues'][0]['value'] ?? '';
            $val = (float)($r['metricValues'][0]['value'] ?? 0);
            if ($key !== '') $out[$key] = $val;
        }
        return $out;
    }

    /**
     * Generate a QuickChart.io <img> tag for embedding in LLM narrative.
     */
    protected function quickChart(string $chartType, array $labels, array $datasets, string $title = ''): string
    {
        $config = [
            'type' => $chartType,
            'data' => ['labels' => $labels, 'datasets' => $datasets],
            'options' => [
                'title' => ['display' => (bool)$title, 'text' => $title],
                'plugins' => ['legend' => ['display' => count($datasets) > 1]],
            ],
        ];
        $url = 'https://quickchart.io/chart?w=600&h=320&bkg=white&c=' . rawurlencode(json_encode($config));
        return '<img src="' . $url . '" alt="' . e($title ?: 'chart') . '" style="max-width:100%;height:auto;margin:1em 0">';
    }

    /**
     * Build charts for preset report types and inject as _charts in data.
     */
    protected function injectCharts(string $type, array &$data): void
    {
        $charts = [];
        try {
            switch ($type) {
                case 'content_decay':
                    $pages = array_slice($data['decayed_pages'] ?? [], 0, 8);
                    if ($pages) {
                        $labels = array_map(fn($p) => substr($p['page'], 0, 25), $pages);
                        $charts[] = $this->quickChart('bar', $labels, [
                            ['label' => 'Previous', 'data' => array_column($pages, 'prev_sessions'), 'backgroundColor' => '#93c5fd'],
                            ['label' => 'Current', 'data' => array_column($pages, 'sessions'), 'backgroundColor' => '#f87171'],
                        ], 'Content Decay — Sessions Comparison');
                    }
                    break;

                case 'anomaly':
                    $swings = $data['big_swings'] ?? [];
                    if ($swings) {
                        $labels = array_map(fn($s) => $s['metric'], $swings);
                        $charts[] = $this->quickChart('bar', $labels, [
                            ['label' => '% Change', 'data' => array_column($swings, 'pct_change'), 'backgroundColor' => array_map(
                                fn($s) => ($s['pct_change'] ?? 0) >= 0 ? '#4ade80' : '#f87171', $swings
                            )],
                        ], 'Week-over-Week Changes (>20%)');
                    }
                    break;

                case 'brand_split':
                    $b = $data['brand']['clicks'] ?? 0;
                    $nb = $data['non_brand']['clicks'] ?? 0;
                    if ($b || $nb) {
                        $charts[] = $this->quickChart('doughnut',
                            ['Brand', 'Non-Brand'],
                            [['data' => [$b, $nb], 'backgroundColor' => ['#60a5fa', '#f97316']]],
                            'Brand vs Non-Brand Clicks'
                        );
                    }
                    break;

                case 'brand_rescue':
                    $bc = $data['brand']['clicks_current'] ?? 0;
                    $bp = $data['brand']['clicks_previous'] ?? 0;
                    $nc = $data['non_brand']['clicks_current'] ?? 0;
                    $np = $data['non_brand']['clicks_previous'] ?? 0;
                    if ($bc || $nc) {
                        $charts[] = $this->quickChart('bar',
                            ['Brand', 'Non-Brand'],
                            [
                                ['label' => 'Previous', 'data' => [$bp, $np], 'backgroundColor' => '#93c5fd'],
                                ['label' => 'Current', 'data' => [$bc, $nc], 'backgroundColor' => '#3b82f6'],
                            ],
                            'Brand vs Non-Brand Clicks (Period Comparison)'
                        );
                    }
                    break;

                case 'striking_distance':
                    $opps = array_slice($data['opportunities'] ?? [], 0, 10);
                    if ($opps) {
                        $labels = array_map(fn($o) => substr($o['query'], 0, 20), $opps);
                        $charts[] = $this->quickChart('bar', $labels, [
                            ['label' => 'Impressions', 'data' => array_column($opps, 'impressions'), 'backgroundColor' => '#60a5fa'],
                        ], 'Top Striking-Distance Keywords by Impressions');
                    }
                    break;

                case 'silent_winners':
                    $sw = array_slice($data['silent_winners'] ?? [], 0, 8);
                    if ($sw) {
                        $labels = array_map(fn($s) => substr($s['query'], 0, 20), $sw);
                        $charts[] = $this->quickChart('bar', $labels, [
                            ['label' => 'Impressions', 'data' => array_column($sw, 'impressions'), 'backgroundColor' => '#a78bfa'],
                            ['label' => 'Clicks', 'data' => array_column($sw, 'clicks'), 'backgroundColor' => '#f87171'],
                        ], 'Silent Winners — Impressions vs Clicks');
                    }
                    break;
            }
        } catch (\Throwable $e) {
            // Don't let chart failures break report generation
        }

        if ($charts) {
            $data['_charts'] = $charts;
        }
    }

    protected function prompt(string $type, array $data): string
    {
        $base = "You are a senior SEO/analytics consultant. Write concise, plain-English HTML (use <h2>, <p>, <ul>, <table> only). No markdown. Be specific with numbers.\n\n"
            . "CRITICAL: All percentage changes and deltas in the Data below are pre-computed. USE THEM EXACTLY. Do not recompute, round differently, or invent numbers. If pct_change is null, say 'no prior data'. If a field is missing, say so — do not guess.\n\n"
            . "TABLE RULES:\n"
            . "- When showing comparison tables, use human-readable date range headers like 'Jan 19 - Apr 16 (Current)' and 'Oct 19 - Jan 16 (Previous)' instead of 'sessions' and 'prev_sessions'. The period dates are in the data.\n"
            . "- Page paths should be clean: /page-name not \\/page-name.\n"
            . "- Include the comparison period at the top of the report so readers know the timeframe.\n"
            . "- If a '_note' field exists in the data, respect it (it indicates missing data sources).\n"
            . "- If a '_charts' array exists, embed each <img> tag in the report AFTER the relevant section heading. These are pre-built chart images — paste them exactly as-is.\n\n"
            . "Data:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

        return $base . match($type) {
            'content_decay' => "Report: CONTENT DECAY. Each decayed_pages row has pre-computed 'pct' (percentage change) and 'delta' (session change). Use them exactly — do not recompute. Sections:\n<h2>What's decaying</h2> table of top 10 decayed pages (page, prev_sessions, sessions [current], pct [% drop]).\n<h2>Likely causes</h2> 2-3 hypotheses grouped by pattern.\n<h2>Recovery actions</h2> 3 specific steps prioritized by traffic lost (use delta).",
            'striking_distance' => "Report: STRIKING-DISTANCE KEYWORDS. Sections:\n<h2>Top opportunities</h2> table of queries ranked 4-20 with high impressions (query, impressions, position, CTR).\n<h2>Quick wins</h2> pick top 5 with specific optimization suggestion each.\n<h2>Strategy</h2> 2-3 sentences on approach.",
            'conversion_leak' => "Report: CONVERSION LEAK. Sections:\n<h2>Leaking pages</h2> table of high-traffic pages with zero/low conversions.\n<h2>Likely reasons</h2> 2-3 hypotheses per pattern.\n<h2>Fixes to prioritize</h2> 3 specific actions.",
            'anomaly' => "Report: WEEKLY ANOMALY SCAN. All deltas are pre-computed in the data. Sections:\n<h2>Biggest changes</h2> start with the items in 'big_swings' (>20% moved). Quote the exact pct_change. Include relevant source_deltas. If big_swings is empty, say 'No metric moved >20% this week — stable week.'\n<h2>Likely causes</h2> hypothesis per anomaly, grounded in which source/page moved.\n<h2>Watch list</h2> 3 things to monitor next week.",
            'brand_split' => "Report: BRAND vs NON-BRAND. Sections:\n<h2>Summary</h2> % split of clicks + impressions.\n<h2>Brand performance</h2> brief + top brand queries.\n<h2>Non-brand performance</h2> brief + top non-brand queries.\n<h2>What this means</h2> interpretation.",
            'silent_winners' => "Report: SILENT WINNERS (GA4+GSC join). Queries rank well (top 10) and get impressions, but barely get clicks — and when users DO arrive, engagement is weak. Sections:\n<h2>The silent losers</h2> table: query, page, impressions, CTR, position, GA4 engagement rate, bounce rate.\n<h2>Why it happens</h2> group by pattern (title/meta mismatch, weak intent match, thin content above fold).\n<h2>Priority fixes</h2> pick top 5 — for each: is it a title/meta problem, intent problem, or on-page problem? Specific action per page.",
            'converting_queries' => "Report: CONVERTING QUERIES YOU'RE LOSING (GA4+GSC join). These pages make the most money — did their Google ranking slip? Sections:\n<h2>Revenue pages at risk</h2> table: page, conversions, sessions, top query, position now, position 90d ago, rank change (positive = worse).\n<h2>Biggest slippers</h2> pages where rank dropped >2 positions. Name them, quantify the click loss risk.\n<h2>Recovery plan</h2> 3 concrete actions prioritized by revenue × rank-drop.",
            'cannibalization' => "Report: KEYWORD CANNIBALIZATION (GA4+GSC join). Queries where multiple of your URLs fight for the same spot. Sections:\n<h2>Top conflicts</h2> table: query, URLs competing (with positions, clicks, and GA4 conversions per URL).\n<h2>Which URL should win</h2> for each conflict — pick the winner based on which URL actually converts. Name the loser, explain why.\n<h2>Fix actions</h2> per conflict: merge, redirect, de-optimize, or internal-link strategy.",
            'brand_rescue' => "Report: BRAND RESCUE vs REAL GROWTH (GA4+GSC join). All pct_change values are pre-computed — quote them exactly. A 'verdict' field summarises the pattern. Sections:\n<h2>Headline</h2> state brand clicks pct_change vs non-brand clicks pct_change, using the exact numbers. Interpret the 'verdict' field (non_brand_decaying_brand_masking = call out the hidden decay; genuine_growth = celebrate; brand_decay_reputation_check = warn; stable_both = boring-week; mixed = nuanced).\n<h2>The real picture</h2> include conversions_current for brand vs non-brand landing pages. Explain which is driving revenue.\n<h2>What to do</h2> 3 actions tailored to the verdict.",
            'llm_traffic' => "Report: LLM TRAFFIC. Detects visitors arriving from AI chatbots/search (ChatGPT, Perplexity, Claude, Gemini, Copilot, etc.). All numbers in 'llm_totals' and 'per_llm' are pre-computed — quote exactly. If 'note' is set, lead with it. Sections:\n<h2>Headline</h2> total LLM sessions current, share_of_total_pct of all sessions, sessions_pct_change vs previous period. Be honest if numbers are tiny — LLM referral traffic is still small for most sites.\n<h2>Which LLMs are sending traffic</h2> table from per_llm: LLM name, sessions, users, conversions. Sort by sessions desc.\n<h2>Top landing pages from LLM traffic</h2> table from top_landing_pages: LLM, page, sessions, conversions. Show top 10.\n<h2>Conversational queries in Search Console</h2> if 'gsc_conversational_query_signals' has rows, table top 10 (query, impressions, clicks, position). Caveat: these are NOT confirmed LLM-driven — they are long/question-form queries which correlate with AI-search behavior. Useful as a 2nd signal.\n<h2>What this means + what to do</h2> 3 specific recommendations (e.g. content optimised for citation, schema for AI Overviews, tracking via UTMs from any AI experiments). If LLM share is 0%, focus on llms.txt + content structure for AI crawlability.",
        };
    }

    /**
     * Prebuilt reports — no LLM, return the full package directly.
     */
    protected function buildPrebuilt(string $type, GoogleService $g): array
    {
        return match($type) {
            'keyword_rankings' => $this->keywordRankings($g, 'web'),
            'keyword_rankings_news' => $this->keywordRankings($g, 'news'),
        };
    }

    /**
     * Query × month pivot of GSC positions. Heatmap-styled HTML narrative.
     * $searchType: 'web' (organic blue links) or 'news' (Top Stories / News tab).
     */
    protected function keywordRankings(GoogleService $g, string $searchType = 'web'): array
    {
        $months = 13;
        $minImps = $searchType === 'news' ? 1 : 10; // news volumes are lower — loosen threshold
        $topN = 50;
        $end = now()->subDay()->toDateString();
        $start = now()->subMonths($months - 1)->startOfMonth()->toDateString();

        $pivot = $g->fetchGscQueryPivot($this->conn->gsc_site_url, $start, $end, $minImps, $topN, $searchType);
        $narrative = $this->renderKeywordRankingsHtml($pivot, $start, $end, $minImps, $topN, $searchType);

        $key = $searchType === 'news' ? 'keyword_rankings_news' : 'keyword_rankings';
        return [
            'type' => $key,
            'title' => self::TYPES[$key]['title'],
            'metrics' => [
                'site' => $this->conn->gsc_site_url,
                'search_type' => $searchType,
                'period' => "$start to $end",
                'min_impressions' => $minImps,
                'top_n' => $topN,
                'months' => $pivot['months'],
                'rows' => $pivot['rows'],
            ],
            'narrative' => $narrative,
        ];
    }

    protected function renderKeywordRankingsHtml(array $pivot, string $start, string $end, int $minImps, int $topN, string $searchType = 'web'): string
    {
        $rows = $pivot['rows'];
        $months = $pivot['months'];

        $totalImps = array_sum(array_column($rows, 'totalImpressions'));
        $totalClicks = array_sum(array_column($rows, 'totalClicks'));

        // Inline CSS scoped under .kr-pivot so it doesn't leak.
        // !important on body max-width overrides report.blade.php's 820px wrapper.
        $html = <<<CSS
<style>
body{max-width:1400px!important}
.kr-wrap{font-size:.88rem}
.kr-summary{background:#f5f8ff;border:1px solid #d8e4ff;border-radius:8px;padding:10px 14px;margin:1em 0;display:flex;gap:18px;flex-wrap:wrap;font-size:.88rem;color:#444}
.kr-summary b{color:#1a73e8}
.kr-legend{display:flex;gap:4px;align-items:center;font-size:.72rem;color:#666;flex-wrap:wrap}
.kr-legend .sw{padding:2px 6px;border-radius:3px;font-weight:600;font-size:.7rem}
.kr-filter{margin:0 0 10px}
.kr-filter input{padding:6px 10px;border:1px solid #cfd8e3;border-radius:6px;font-size:.9rem;min-width:260px}
.kr-scroll{overflow:auto;max-height:75vh;border:1px solid #e3e8ee;border-radius:8px;background:#fff}
table.kr-pivot{border-collapse:collapse;font-size:.82rem;width:max-content;min-width:100%;margin:0}
table.kr-pivot thead th{position:sticky;top:0;background:#f8fafc;z-index:3;border-bottom:2px solid #cfd8e3;padding:8px 10px;font-weight:600;color:#555;text-align:center;white-space:nowrap}
table.kr-pivot thead th.q{text-align:left;left:0;z-index:4;min-width:260px;max-width:360px;border-right:2px solid #cfd8e3}
table.kr-pivot thead th.num{min-width:80px}
table.kr-pivot tbody td{padding:6px 8px;border-bottom:1px solid #eef1f5;text-align:center;font-variant-numeric:tabular-nums}
table.kr-pivot tbody td.q{position:sticky;left:0;background:#fff;text-align:left;font-weight:500;color:#222;z-index:2;border-right:2px solid #cfd8e3;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
table.kr-pivot tbody tr:hover td.q{background:#f5f8ff}
table.kr-pivot tbody tr:hover td{background:#fafcff}
table.kr-pivot tbody td.num{color:#555;min-width:60px}
table.kr-pivot tbody td.empty{color:#ccc}
.kr-p1{background:#065f46;color:#fff}
.kr-p2{background:#10b981;color:#fff}
.kr-p3{background:#34d399;color:#064e3b}
.kr-p4{background:#a7f3d0;color:#064e3b}
.kr-p5{background:#fef3c7;color:#78350f}
.kr-p6{background:#fde68a;color:#78350f}
.kr-p7{background:#fbbf24;color:#78350f}
.kr-p8{background:#fb923c;color:#7c2d12}
.kr-p9{background:#ef4444;color:#fff}
.kr-p10{background:#7f1d1d;color:#fff}
</style>
CSS;

        $typeLabel = $searchType === 'news' ? 'News (Top Stories / News tab)' : 'Web (organic blue links)';
        $html .= '<div class="kr-wrap">';
        $html .= '<p><b>' . htmlspecialchars($this->conn->gsc_site_url) . '</b> · '
            . htmlspecialchars($start) . ' → ' . htmlspecialchars($end)
            . ' · <b>' . $typeLabel . '</b> · top ' . $topN . ' queries (≥ ' . $minImps . ' total impressions) · impression-weighted avg position per month</p>';

        $html .= '<div class="kr-summary">'
            . '<span><b>' . number_format(count($rows)) . '</b> queries · <b>' . count($months) . '</b> months · <b>'
            . number_format($totalImps) . '</b> impressions · <b>' . number_format($totalClicks) . '</b> clicks</span>'
            . '<div class="kr-legend">Position:'
            . '<span class="sw kr-p1">1-2</span><span class="sw kr-p2">3</span><span class="sw kr-p3">4-5</span>'
            . '<span class="sw kr-p4">6-10</span><span class="sw kr-p5">11-15</span><span class="sw kr-p6">16-20</span>'
            . '<span class="sw kr-p7">21-30</span><span class="sw kr-p8">31-50</span>'
            . '<span class="sw kr-p9">51-80</span><span class="sw kr-p10">81+</span></div></div>';

        $html .= '<div class="kr-filter"><input type="text" placeholder="Filter query (live)…" '
            . 'oninput="(function(q){q=q.toLowerCase().trim();document.querySelectorAll(\'#krBody tr\').forEach(t=>{t.style.display=!q||t.dataset.q.includes(q)?\'\':\'none\'})})(this.value)"></div>';

        if (empty($rows)) {
            $html .= '<p><i>No queries matched. The site may be new or have very low search traffic in this window.</i></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<div class="kr-scroll"><table class="kr-pivot"><thead><tr>'
            . '<th class="q">Query</th><th class="num">Impr.</th><th class="num">Clicks</th>';
        foreach ($months as $ym) {
            $html .= '<th>' . htmlspecialchars($ym) . '</th>';
        }
        $html .= '</tr></thead><tbody id="krBody">';

        foreach ($rows as $row) {
            $q = htmlspecialchars($row['query']);
            $html .= '<tr data-q="' . strtolower($q) . '">'
                . '<td class="q" title="' . $q . '">' . $q . '</td>'
                . '<td class="num">' . number_format($row['totalImpressions']) . '</td>'
                . '<td class="num">' . number_format($row['totalClicks']) . '</td>';
            foreach ($months as $ym) {
                $cell = $row['months'][$ym] ?? null;
                $pos = $cell['position'] ?? null;
                if ($pos === null) {
                    $html .= '<td class="empty">—</td>';
                } else {
                    $cls = $this->posClass($pos);
                    $imps = $cell['impressions'] ?? 0;
                    $clicks = $cell['clicks'] ?? 0;
                    $title = "Pos $pos · " . number_format($imps) . ' impressions · ' . number_format($clicks) . ' clicks';
                    $html .= '<td class="' . $cls . '" title="' . htmlspecialchars($title) . '">' . $pos . '</td>';
                }
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></div>';
        return $html;
    }

    protected function posClass(float $p): string
    {
        if ($p <= 2) return 'kr-p1';
        if ($p <= 3.5) return 'kr-p2';
        if ($p <= 5.5) return 'kr-p3';
        if ($p <= 10.5) return 'kr-p4';
        if ($p <= 15.5) return 'kr-p5';
        if ($p <= 20.5) return 'kr-p6';
        if ($p <= 30.5) return 'kr-p7';
        if ($p <= 50.5) return 'kr-p8';
        if ($p <= 80.5) return 'kr-p9';
        return 'kr-p10';
    }
}
