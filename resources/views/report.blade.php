<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $report->title ?? 'Report' }}</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon-180.png">
<meta name="robots" content="noindex, nofollow, noarchive">
<style>
body{font-family:system-ui,Arial,sans-serif;max-width:820px;margin:40px auto;padding:20px;color:#222;line-height:1.6}
h1{border-bottom:2px solid #1a73e8;padding-bottom:.3em;margin-bottom:.2em}
h2{color:#1a73e8;margin-top:2em;font-size:1.25rem}
table{border-collapse:collapse;margin:1em 0;width:100%}
th,td{border:1px solid #ddd;padding:8px 12px;text-align:left;font-size:.92rem}
th{background:#f5f8ff}
.meta{color:#666;font-size:.9rem;margin-bottom:1.5em}
.btn{display:inline-block;background:#1a73e8;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;margin:.3em .5em .3em 0}
.btn-sec{background:#fff;color:#1a73e8;border:1px solid #1a73e8}
.error-narrative{background:#fff5f5;border-left:3px solid #d93025;padding:10px 16px;border-radius:0 6px 6px 0}
.retry-box{background:#fffbea;border:1px solid #ffe58f;padding:16px;border-radius:8px;margin:1em 0}
.retry-box form{margin:0;display:inline}
.retry-box button{background:#1a73e8;color:#fff;border:0;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:.95rem}
.cta{border:1px solid #e3e3e3;border-radius:10px;padding:22px;margin:2em 0 1em}
.cta h3{margin:0 0 .4em;font-size:1.1rem}
.cta p{margin:.3em 0;font-size:.92rem;color:#555}
.cta-action{display:inline-block;background:#1a73e8;color:#fff;padding:8px 18px;border-radius:6px;text-decoration:none;margin-top:.8em;font-size:.9rem}
.cta-help{border-color:#d8e4ff;background:linear-gradient(135deg,#f5f8ff 0%,#fff 100%)}
.cta-weekly{border-color:#d8ffd8;background:linear-gradient(135deg,#f5fff5 0%,#fff 100%)}
.past-reports{margin-top:2.5em;border-top:1px solid #eee;padding-top:1.5em}
.past-reports h3{font-size:1rem;color:#555;margin:0 0 .8em}
.past-list{list-style:none;padding:0;margin:0}
.past-list li{padding:6px 0;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
.past-list li:last-child{border-bottom:0}
.past-list a{color:#1a73e8;text-decoration:none;font-size:.9rem}
.past-list a:hover{text-decoration:underline}
.past-list .past-date{color:#999;font-size:.8rem}
.past-list .past-type{background:#f0f7ff;color:#1a73e8;font-size:.72rem;padding:2px 6px;border-radius:3px;margin-left:6px}
</style>
@if(($isPdf ?? false) && in_array($report->type, ['keyword_rankings', 'keyword_rankings_news']))
<style>
@page { size: A4 landscape; margin: 6mm; }
body { max-width: 100% !important; margin: 0 !important; padding: 0 !important; font-size: 9pt; }
h1 { font-size: 13pt; margin: 0 0 2mm !important; padding-bottom: 2mm !important }
.meta { font-size: 8pt; margin-bottom: 2mm !important }
.btn, .cta, .past-reports, .retry-box { display: none !important; }
.kr-summary, .kr-filter { display: none !important; }
.kr-scroll { max-height: none !important; overflow: visible !important; border: 0 !important; }
.kr-pivot { font-size: 6.5pt !important; width: 100% !important; }
.kr-pivot th, .kr-pivot td { padding: 1px 3px !important; }
.kr-pivot thead th.q, .kr-pivot tbody td.q { min-width: 0 !important; max-width: 90mm !important; position: static !important; }
.kr-pivot thead th { position: static !important; }
.kr-pivot tbody td.q { position: static !important; }
</style>
@endif
</head>
<body>
<h1>{{ $report->title ?? 'Analytics Report' }}</h1>
<p class="meta">
{{ $report->connection->email }} ·
Generated {{ $report->created_at->format('M j, Y H:i') }}
</p>

@php
    $isError = str_contains($report->narrative, 'error-narrative') || str_contains($report->narrative, 'exceeded max iterations');
    $originalPrompt = $report->metrics['prompt'] ?? null;

    // Fix URLs in narrative: remove backslash escapes, make paths clickable
    $cleanNarrative = $report->narrative;
    // Fix \/ → /
    $cleanNarrative = str_replace('\\/', '/', $cleanNarrative);
    // Make bare paths clickable (e.g. /some-page → <a href="/some-page">/some-page</a>)
    // Only in <td> cells to avoid breaking existing links
    $siteUrl = $report->connection->gsc_site_url ?? '';
    $siteHost = $siteUrl ? rtrim(preg_replace('#^sc-domain:#', 'https://', $siteUrl), '/') : '';
    if ($siteHost) {
        $cleanNarrative = preg_replace_callback(
            '#<td>(/[a-zA-Z0-9\-_/\.]+)</td>#',
            fn($m) => '<td><a href="' . $siteHost . $m[1] . '" target="_blank" rel="noopener">' . $m[1] . '</a></td>',
            $cleanNarrative
        );
    }
@endphp

<a href="{{ route('report.pdf', $report) }}" class="btn">Download PDF</a>
<a href="/" class="btn btn-sec">← Run another report</a>

{!! $cleanNarrative !!}

@if($isError && $originalPrompt && $report->type === 'ask')
<div class="retry-box">
<p><strong>Retry this question?</strong> The agent sometimes stalls — a second run usually works.</p>
<form method="post" action="{{ route('ask.run') }}">
@csrf
<input type="hidden" name="prompt" value="{{ $originalPrompt }}">
<input type="hidden" name="ga4_property_id" value="{{ $report->connection->ga4_property_id }}">
<input type="hidden" name="gsc_site_url" value="{{ $report->connection->gsc_site_url }}">
<button type="submit">Retry ↻</button>
</form>
</div>
@endif

{{-- CTA: Need help implementing? --}}
<div class="cta cta-help">
<h3>Need help implementing these recommendations?</h3>
<p>Send us this report — we'll review your data and reply with a custom action plan + quote.</p>
<a class="cta-action" href="mailto:info@seokru.com?subject={{ rawurlencode('Help with: ' . ($report->title ?? 'Analytics Report')) }}&body={{ rawurlencode('Report: ' . route('report.show', $report) . "\n\nI need help with:\n") }}">
Send to SEOKRU →
</a>
</div>

{{-- CTA: Want this weekly? --}}
<div class="cta cta-weekly">
<h3>Want this report every week?</h3>
<p>Get a fresh {{ $report->title ?? 'report' }} delivered to your inbox every Monday morning. Set it and forget it.</p>
<a class="cta-action" href="mailto:info@seokru.com?subject={{ rawurlencode('Weekly report request: ' . ($report->title ?? 'Analytics Report')) }}&body={{ rawurlencode('Report: ' . route('report.show', $report) . "\n\nI'd like to receive this report weekly.\n") }}" style="background:#1e8e3e">
Request weekly delivery →
</a>
</div>

{{-- Previous reports --}}
@php
    $pastReports = \App\Models\Report::where('connection_id', $report->connection_id)
        ->where('id', '!=', $report->id)
        ->latest()
        ->take(10)
        ->get(['id', 'slug', 'type', 'title', 'created_at']);
@endphp

@if($pastReports->count())
<div class="past-reports">
<h3>Your previous reports</h3>
<ul class="past-list">
@foreach($pastReports as $pr)
<li>
<span>
<a href="{{ route('report.show', $pr) }}">{{ Str::limit($pr->title, 60) }}</a>
<span class="past-type">{{ $pr->type }}</span>
</span>
<span class="past-date">{{ $pr->created_at->format('M j, H:i') }}</span>
</li>
@endforeach
</ul>
</div>
@endif

</body>
</html>
