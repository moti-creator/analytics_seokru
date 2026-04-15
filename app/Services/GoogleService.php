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

}

