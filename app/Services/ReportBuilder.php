<?php

namespace App\Services;

use App\Models\Connection;
use Illuminate\Support\Facades\Http;

class ReportBuilder
{
    public const TYPES = [
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
    ];

    public function __construct(public Connection $conn) {}

    public function build(string $type): array
    {
        $g = new GoogleService($this->conn);
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
        };
        $narrative = (new GeminiService())->raw($this->prompt($type, $data));
        return [
            'type' => $type,
            'title' => self::TYPES[$type]['title'],
            'metrics' => $data,
            'narrative' => $narrative,
        ];
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

        $ga = $g->fetchGa4($this->conn->ga4_property_id, $start, $end);
        $gaPrev = $g->fetchGa4($this->conn->ga4_property_id, $prevStart, $prevEnd);
        $gsc = $g->fetchGsc($this->conn->gsc_site_url, $start, $end);
        $gscPrev = $g->fetchGsc($this->conn->gsc_site_url, $prevStart, $prevEnd);

        return [
            'period_current' => "$start to $end",
            'period_previous' => "$prevStart to $prevEnd",
            'ga4_totals_current' => $ga['totals']['rows'][0] ?? null,
            'ga4_totals_previous' => $gaPrev['totals']['rows'][0] ?? null,
            'ga4_top_sources_current' => $ga['topSources']['rows'] ?? [],
            'ga4_top_sources_previous' => $gaPrev['topSources']['rows'] ?? [],
            'gsc_totals_current' => $gsc['totals']['rows'][0] ?? null,
            'gsc_totals_previous' => $gscPrev['totals']['rows'][0] ?? null,
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
                'conversions_current' => round($brandConv, 2),
            ],
            'non_brand' => [
                'clicks_current' => $cur['nonBrandClicks'],
                'clicks_previous' => $prev['nonBrandClicks'],
                'conversions_current' => round($nonBrandConv, 2),
            ],
        ];
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

    protected function prompt(string $type, array $data): string
    {
        $base = "You are a senior SEO/analytics consultant. Write concise, plain-English HTML (use <h2>, <p>, <ul>, <table> only). No markdown. Be specific with numbers.\n\nData:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

        return $base . match($type) {
            'content_decay' => "Report: CONTENT DECAY. Sections:\n<h2>What's decaying</h2> table of top 10 decayed pages (page, prev sessions, current, % drop).\n<h2>Likely causes</h2> 2-3 hypotheses.\n<h2>Recovery actions</h2> 3 specific steps.",
            'striking_distance' => "Report: STRIKING-DISTANCE KEYWORDS. Sections:\n<h2>Top opportunities</h2> table of queries ranked 4-20 with high impressions (query, impressions, position, CTR).\n<h2>Quick wins</h2> pick top 5 with specific optimization suggestion each.\n<h2>Strategy</h2> 2-3 sentences on approach.",
            'conversion_leak' => "Report: CONVERSION LEAK. Sections:\n<h2>Leaking pages</h2> table of high-traffic pages with zero/low conversions.\n<h2>Likely reasons</h2> 2-3 hypotheses per pattern.\n<h2>Fixes to prioritize</h2> 3 specific actions.",
            'anomaly' => "Report: WEEKLY ANOMALY SCAN. Compute % deltas yourself from current vs previous. Sections:\n<h2>Biggest changes</h2> list metrics with >20% swing.\n<h2>Likely causes</h2> hypothesis per anomaly.\n<h2>Watch list</h2> things to monitor next week.",
            'brand_split' => "Report: BRAND vs NON-BRAND. Sections:\n<h2>Summary</h2> % split of clicks + impressions.\n<h2>Brand performance</h2> brief + top brand queries.\n<h2>Non-brand performance</h2> brief + top non-brand queries.\n<h2>What this means</h2> interpretation.",
            'silent_winners' => "Report: SILENT WINNERS (GA4+GSC join). Queries rank well (top 10) and get impressions, but barely get clicks — and when users DO arrive, engagement is weak. Sections:\n<h2>The silent losers</h2> table: query, page, impressions, CTR, position, GA4 engagement rate, bounce rate.\n<h2>Why it happens</h2> group by pattern (title/meta mismatch, weak intent match, thin content above fold).\n<h2>Priority fixes</h2> pick top 5 — for each: is it a title/meta problem, intent problem, or on-page problem? Specific action per page.",
            'converting_queries' => "Report: CONVERTING QUERIES YOU'RE LOSING (GA4+GSC join). These pages make the most money — did their Google ranking slip? Sections:\n<h2>Revenue pages at risk</h2> table: page, conversions, sessions, top query, position now, position 90d ago, rank change (positive = worse).\n<h2>Biggest slippers</h2> pages where rank dropped >2 positions. Name them, quantify the click loss risk.\n<h2>Recovery plan</h2> 3 concrete actions prioritized by revenue × rank-drop.",
            'cannibalization' => "Report: KEYWORD CANNIBALIZATION (GA4+GSC join). Queries where multiple of your URLs fight for the same spot. Sections:\n<h2>Top conflicts</h2> table: query, URLs competing (with positions, clicks, and GA4 conversions per URL).\n<h2>Which URL should win</h2> for each conflict — pick the winner based on which URL actually converts. Name the loser, explain why.\n<h2>Fix actions</h2> per conflict: merge, redirect, de-optimize, or internal-link strategy.",
            'brand_rescue' => "Report: BRAND RESCUE vs REAL GROWTH (GA4+GSC join). Is brand traffic masking non-brand decay? Sections:\n<h2>Headline</h2> compute brand % change vs non-brand % change, clicks current vs previous. State clearly which is growing and which is shrinking.\n<h2>The real picture</h2> if total traffic looks stable but non-brand is declining, call it out. Include conversions from brand-landing pages vs non-brand-landing pages.\n<h2>What to do</h2> 3 actions — if non-brand declining: content/rank work; if brand declining: reputation/PR signal check.",
        };
    }
}
