<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Connection;
use App\Models\Report;
use App\Services\GoogleService;
use App\Services\ReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function landing()
    {
        return view('landing', [
            'types' => ReportBuilder::TYPES,
            'connection_id' => session('connection_id'),
        ]);
    }

    public function start(string $type)
    {
        abort_unless($type === 'ask' || isset(ReportBuilder::TYPES[$type]), 404);
        session(['report_type' => $type]);

        // Already connected? skip OAuth.
        if (session('connection_id') && Connection::find(session('connection_id'))) {
            return $type === 'ask'
                ? redirect()->route('ask.form')
                : redirect()->route('connect');
        }
        return redirect('/auth/google');
    }

    public function connectForm()
    {
        $conn = Connection::find(session('connection_id'));
        if (!$conn) return redirect('/');
        $type = session('report_type', 'anomaly');

        if ($type === 'ask') {
            return redirect()->route('ask.form');
        }

        $g = new GoogleService($conn);
        $properties = $g->listGa4Properties();
        $sites = $g->listGscSites();

        return view('connect', compact('conn', 'properties', 'sites', 'type'));
    }

    public function generate(Request $r)
    {
        $conn = Connection::find(session('connection_id'));
        if (!$conn) return redirect('/');
        $type = session('report_type', 'anomaly');

        $r->validate([
            'ga4_property_id' => 'nullable',
            'gsc_site_url' => 'nullable',
        ]);

        $conn->update([
            'ga4_property_id' => $r->ga4_property_id ?: $conn->ga4_property_id,
            'gsc_site_url' => $r->gsc_site_url ?: $conn->gsc_site_url,
        ]);

        $built = (new ReportBuilder($conn))->build($type);

        $report = Report::create([
            'connection_id' => $conn->id,
            'type' => $built['type'],
            'title' => $built['title'],
            'metrics' => $built['metrics'],
            'narrative' => $built['narrative'],
        ]);

        return redirect()->route('report.show', $report);
    }

    public function show(Report $report)
    {
        $this->ensureOwner($report);
        $report->load('connection');
        return view('report', compact('report'));
    }

    public function pdf(Report $report)
    {
        $this->ensureOwner($report);
        $report->load('connection');
        $pdf = Pdf::loadView('report', compact('report'));
        return $pdf->download("{$report->type}-{$report->slug}.pdf");
    }

    /**
     * Owner-only access. Session connection_id must match report.connection_id.
     */
    protected function ensureOwner(Report $report): void
    {
        $sid = session('connection_id');
        if (!$sid || (int)$sid !== (int)$report->connection_id) {
            abort(403, 'You must be signed in with the Google account that created this report.');
        }
    }
}
