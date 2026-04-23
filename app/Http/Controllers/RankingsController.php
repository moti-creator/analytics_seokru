<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Services\GoogleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RankingsController extends Controller
{
    public function show(Request $r)
    {
        $conn = session('connection_id') ? Connection::find(session('connection_id')) : null;
        if (!$conn) return redirect('/');
        if (!$conn->gsc_site_url) return redirect('/')->with('flash', 'Select a Search Console site first.');

        $months = max(1, min(24, (int)$r->query('months', 13)));
        $minImps = max(1, min(1000, (int)$r->query('min', 10)));
        $topN = max(10, min(200, (int)$r->query('top', 50)));

        $end = now()->subDay()->toDateString();
        $start = now()->subMonths($months - 1)->startOfMonth()->toDateString();

        $key = sprintf('rankings:%d:%s:%s:%d:%d', $conn->id, $start, $end, $minImps, $topN);
        $pivot = Cache::remember($key, 60 * 60 * 6, function () use ($conn, $start, $end, $minImps, $topN) {
            $g = new GoogleService($conn);
            return $g->fetchGscQueryPivot($conn->gsc_site_url, $start, $end, $minImps, $topN);
        });

        return view('rankings', [
            'conn' => $conn,
            'pivot' => $pivot,
            'months' => $months,
            'minImps' => $minImps,
            'topN' => $topN,
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function csv(Request $r)
    {
        $conn = session('connection_id') ? Connection::find(session('connection_id')) : null;
        if (!$conn || !$conn->gsc_site_url) return redirect('/');

        $months = max(1, min(24, (int)$r->query('months', 13)));
        $minImps = max(1, min(1000, (int)$r->query('min', 10)));
        $topN = max(10, min(500, (int)$r->query('top', 50)));

        $end = now()->subDay()->toDateString();
        $start = now()->subMonths($months - 1)->startOfMonth()->toDateString();

        $g = new GoogleService($conn);
        $pivot = $g->fetchGscQueryPivot($conn->gsc_site_url, $start, $end, $minImps, $topN);

        $filename = 'rankings-' . preg_replace('/[^a-z0-9]/i', '', $conn->gsc_site_url) . '-' . now()->toDateString() . '.csv';
        return response()->streamDownload(function () use ($pivot) {
            $out = fopen('php://output', 'w');
            $header = ['Query', 'Total Impressions', 'Total Clicks'];
            foreach ($pivot['months'] as $ym) $header[] = $ym;
            fputcsv($out, $header);
            foreach ($pivot['rows'] as $row) {
                $line = [$row['query'], $row['totalImpressions'], $row['totalClicks']];
                foreach ($pivot['months'] as $ym) {
                    $line[] = $row['months'][$ym]['position'] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
