<?php

namespace App\Services;

use App\Models\Connection;
use Google\Client;
use Illuminate\Support\Facades\Http;

class GoogleService
{
    public function __construct(public Connection $conn) {}

    protected function client(): Client
    {
        $c = new Client();
        $c->setClientId(config('services.google.client_id'));
        $c->setClientSecret(config('services.google.client_secret'));
        $c->setAccessToken([
            'access_token' => $this->conn->access_token,
            'refresh_token' => $this->conn->refresh_token,
            'expires_in' => max(1, now()->diffInSeconds($this->conn->expires_at, false)),
            'created' => $this->conn->updated_at->timestamp,
        ]);

        if ($c->isAccessTokenExpired() && $this->conn->refresh_token) {
            $c->fetchAccessTokenWithRefreshToken($this->conn->refresh_token);
            $t = $c->getAccessToken();
            $this->conn->update([
                'access_token' => $t['access_token'],
                'expires_at' => now()->addSeconds($t['expires_in']),
            ]);
        }
        return $c;
    }

    protected function token(): string
    {
        return $this->client()->getAccessToken()['access_token'];
    }

    public function publicToken(): string
    {
        return $this->token();
    }

    public function listGa4Properties(): array
    {
        $accounts = Http::withToken($this->token())
            ->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries')
            ->json('accountSummaries', []);

        $out = [];
        foreach ($accounts as $a) {
            foreach ($a['propertySummaries'] ?? [] as $p) {
                $out[] = [
                    'id' => str_replace('properties/', '', $p['property']),
                    'name' => $p['displayName'] . ' (' . $a['displayName'] . ')',
                ];
            }
        }
        return $out;
    }

    public function listGscSites(): array
    {
        $sites = Http::withToken($this->token())
            ->get('https://www.googleapis.com/webmasters/v3/sites')
            ->json('siteEntry', []);

        return collect($sites)
            ->filter(fn($s) => in_array($s['permissionLevel'] ?? '', ['siteOwner','siteFullUser','siteRestrictedUser']))
            ->map(fn($s) => ['url' => $s['siteUrl'], 'permission' => $s['permissionLevel']])
            ->values()->all();
    }
    public function fetchGa4(string $propertyId, string $start, string $end): array
    {
        $body = [
            'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'conversions'],
                ['name' => 'engagementRate'],
            ],
        ];
        $totals = Http::withToken($this->token())
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", $body)
            ->json();

        $topPages = Http::withToken($this->token())
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", [
                'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
                'dimensions' => [['name' => 'pagePath']],
                'metrics' => [['name' => 'sessions']],
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => 10,
            ])->json();

        $topSources = Http::withToken($this->token())
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", [
                'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
                'dimensions' => [['name' => 'sessionSource']],
                'metrics' => [['name' => 'sessions']],
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => 10,
            ])->json();

        return ['totals' => $totals, 'topPages' => $topPages, 'topSources' => $topSources];
    }

    public function fetchGsc(string $siteUrl, string $start, string $end): array
    {
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';

        $totals = Http::withToken($this->token())
            ->post($url, ['startDate' => $start, 'endDate' => $end, 'dimensions' => []])
            ->json();

        $topQueries = Http::withToken($this->token())
            ->post($url, [
                'startDate' => $start, 'endDate' => $end,
                'dimensions' => ['query'], 'rowLimit' => 10,
            ])->json();

        $topPages = Http::withToken($this->token())
            ->post($url, [
                'startDate' => $start, 'endDate' => $end,
                'dimensions' => ['page'], 'rowLimit' => 10,
            ])->json();

        return ['totals' => $totals, 'topQueries' => $topQueries, 'topPages' => $topPages];
    }

    /**
     * Query × month pivot for keyword rankings dashboard.
     * Pulls query+date rows (paginated if needed), aggregates position weighted
     * by impressions per query × YYYY-MM, ranks queries by total impressions desc.
     *
     * Returns:
     *   months: ['25-04', '25-05', ...] ascending
     *   rows: [['query' => ..., 'totalImpressions' => ..., 'totalClicks' => ...,
     *           'months' => ['25-04' => ['position' => 5.2, 'impressions' => 300], ...]], ...]
     */
    public function fetchGscQueryPivot(string $siteUrl, string $start, string $end, int $minImpressions = 10, int $topN = 50, string $searchType = 'web'): array
    {
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';

        $all = [];
        $startRow = 0;
        $pageSize = 25000;
        for ($i = 0; $i < 10; $i++) {
            $res = Http::withToken($this->token())
                ->timeout(60)
                ->post($url, [
                    'startDate' => $start, 'endDate' => $end,
                    'dimensions' => ['query', 'date'],
                    'type' => $searchType, // 'web' excludes news/image/video SERPs
                    'rowLimit' => $pageSize,
                    'startRow' => $startRow,
                    'dataState' => 'all',
                ])->json();
            $rows = $res['rows'] ?? [];
            if (empty($rows)) break;
            $all = array_merge($all, $rows);
            if (count($rows) < $pageSize) break;
            $startRow += $pageSize;
        }

        // Build month list covering [start, end].
        $months = [];
        $cursor = \Carbon\Carbon::parse($start)->startOfMonth();
        $last = \Carbon\Carbon::parse($end)->startOfMonth();
        while ($cursor <= $last) {
            $months[] = $cursor->format('y-m');
            $cursor->addMonth();
        }

        // Aggregate per query × month.
        $agg = [];
        foreach ($all as $r) {
            [$query, $date] = $r['keys'];
            $ym = \Carbon\Carbon::parse($date)->format('y-m');
            $imps = (int)($r['impressions'] ?? 0);
            $clicks = (int)($r['clicks'] ?? 0);
            $posSum = ($r['position'] ?? 0) * $imps; // impression-weighted
            if (!isset($agg[$query])) {
                $agg[$query] = ['query' => $query, 'totalImpressions' => 0, 'totalClicks' => 0, 'months' => []];
            }
            $q = &$agg[$query];
            $q['totalImpressions'] += $imps;
            $q['totalClicks'] += $clicks;
            if (!isset($q['months'][$ym])) {
                $q['months'][$ym] = ['impressions' => 0, 'posSum' => 0, 'clicks' => 0];
            }
            $q['months'][$ym]['impressions'] += $imps;
            $q['months'][$ym]['posSum'] += $posSum;
            $q['months'][$ym]['clicks'] += $clicks;
            unset($q);
        }

        // Drop queries below threshold, compute per-month avg position.
        $result = [];
        foreach ($agg as $q) {
            if ($q['totalImpressions'] < $minImpressions) continue;
            $monthsOut = [];
            foreach ($q['months'] as $ym => $m) {
                $monthsOut[$ym] = [
                    'position' => $m['impressions'] > 0 ? round($m['posSum'] / $m['impressions'], 2) : null,
                    'impressions' => $m['impressions'],
                    'clicks' => $m['clicks'],
                ];
            }
            $q['months'] = $monthsOut;
            $result[] = $q;
        }

        // Sort by total impressions desc, take top N.
        usort($result, fn($a, $b) => $b['totalImpressions'] <=> $a['totalImpressions']);
        $result = array_slice($result, 0, $topN);

        return ['months' => $months, 'rows' => $result];
    }

}

