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
        };
    }
}
