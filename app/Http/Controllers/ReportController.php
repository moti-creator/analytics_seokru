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
        $conn = session('connection_id') ? Connection::find(session('connection_id')) : null;
        $properties = [];
        $sites = [];
        $recent = collect();

        if ($conn) {
            try {
                $g = new GoogleService($conn);
                $properties = $g->listGa4Properties();
                $sites = $g->listGscSites();
            } catch (\Throwable $e) {}

            $recent = Report::where('connection_id', $conn->id)
                ->latest()->take(5)
                ->get(['id', 'slug', 'type', 'title', 'created_at']);
        }

        $hasProperty = $conn && ($conn->ga4_property_id || $conn->gsc_site_url);

        return view('landing', [
            'conn' => $conn,
            'hasProperty' => $hasProperty,
            'properties' => $properties,
            'sites' => $sites,
            'types' => ReportBuilder::TYPES,
            'recent' => $recent,
        ]);
    }

    /**
     * Hero textbox handler. Save prompt, then jump to ask flow (OAuth if needed).
     */
    public function askStart(Request $r)
    {
        $r->validate(['prompt' => 'required|string|max:2000']);
        session(['pending_prompt' => $r->prompt, 'report_type' => 'ask']);

        $conn = session('connection_id') ? Connection::find(session('connection_id')) : null;
        if ($conn) {
            // Has property already? Skip connect, go to ask.
            if ($conn->ga4_property_id || $conn->gsc_site_url) {
                return redirect()->route('ask.form');
            }
            return redirect()->route('connect');
        }
        return redirect('/auth/google');
    }

    /**
     * Entry point from landing cards. Stores type, routes through OAuth if needed.
     */
    public function start(string $type)
    {
        abort_unless($type === 'ask' || isset(ReportBuilder::TYPES[$type]), 404);
        session(['report_type' => $type]);

        $conn = session('connection_id') ? Connection::find(session('connection_id')) : null;
        if ($conn) {
            if ($type === 'ask') return redirect()->route('ask.form');
            // Has property? Generate directly. Else pick property first.
            if ($conn->ga4_property_id || $conn->gsc_site_url) {
                return redirect()->route('generate.direct', $type);
            }
            return redirect()->route('connect');
        }
        return redirect('/auth/google');
    }

    /**
     * Property selector. Shown once after first OAuth, or when user clicks "change property".
     */
    public function connectForm()
    {
        $conn = Connection::find(session('connection_id'));
        if (!$conn) return redirect('/');
        $type = session('report_type');

        if ($type === 'ask') {
            return redirect()->route('ask.form');
        }

        $g = new GoogleService($conn);
        $properties = $g->listGa4Properties();
        $sites = $g->listGscSites();

        return view('connect', compact('conn', 'properties', 'sites', 'type'));
    }

    /**
     * Dashboard redirects to landing — single unified page handles all states.
     */
    public function dashboard()
    {
        return redirect('/');
    }

    /**
     * Update property selection from dashboard top bar.
     */
    public function updateProperty(Request $r)
    {
        $conn = Connection::find(session('connection_id'));
        if (!$conn) return redirect('/');

        $conn->update([
            'ga4_property_id' => $r->ga4_property_id ?: null,
            'gsc_site_url' => $r->gsc_site_url ?: null,
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Generate from dashboard card click (property already selected).
     */
    public function generateDirect(string $type)
    {
        $conn = Connection::find(session('connection_id'));
        if (!$conn) return redirect('/');
        abort_unless(isset(ReportBuilder::TYPES[$type]), 404);

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

    /**
     * Generate from connect form (first time — saves property then generates).
     */
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

    protected function ensureOwner(Report $report): void
    {
        $sid = session('connection_id');
        if (!$sid || (int)$sid !== (int)$report->connection_id) {
            abort(403, 'You must be signed in with the Google account that created this report.');
        }
    }
}
