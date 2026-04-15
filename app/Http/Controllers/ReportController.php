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
        return view('landing', ['types' => ReportBuilder::TYPES]);
    }

    public function start(string $type)
    {
        abort_unless(isset(ReportBuilder::TYPES[$type]), 404);
        session(['report_type' => $type]);
        return redirect('/auth/google');
    }

    public function connectForm()
    {
        $conn = Connection::find(session('connection_id'));
        if (!$conn) return redirect('/');
        $type = session('report_type', 'anomaly');

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

        return redirect()->route('report.show', $report->id);
    }

    public function show($id)
    {
        $report = Report::with('connection')->findOrFail($id);
        return view('report', compact('report'));
    }

    public function pdf($id)
    {
        $report = Report::with('connection')->findOrFail($id);
        $pdf = Pdf::loadView('report', compact('report'));
        return $pdf->download("{$report->type}-{$report->id}.pdf");
    }
}
