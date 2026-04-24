<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Pick Property</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon-180.png">
<meta name="robots" content="noindex, nofollow, noarchive">
<style>
body{font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:20px;line-height:1.6}
label{display:block;margin:1.2em 0 .3em;font-weight:600}
select,button{width:100%;padding:12px;font-size:1rem;border-radius:6px;border:1px solid #ccc}
button{background:#1a73e8;color:#fff;border:none;font-weight:600;cursor:pointer;margin-top:1.5em}
button:hover{background:#1557b0}
.muted{color:#888;font-size:.9rem}
.warn{color:#c00;font-size:.88rem;margin:.5em 0}
.needs{background:#f0f7ff;padding:8px 14px;border-radius:6px;font-size:.88rem;color:#555;margin:1em 0}
.needs strong{color:#1a73e8}
</style></head>
<body>
<h2>Connected as {{ $conn->email }} ✓</h2>
<p class="muted">Pick which GA4 property and/or Search Console site for your report. You can select just one — we'll work with what you have.</p>

@php
    $needs = \App\Services\ReportBuilder::TYPES[$type]['needs'] ?? ['ga4','gsc'];
@endphp

<div class="needs">
<strong>{{ \App\Services\ReportBuilder::TYPES[$type]['title'] ?? $type }}</strong> uses:
@foreach($needs as $n)
<span>{{ strtoupper($n) }}</span>@if(!$loop->last) + @endif
@endforeach
</div>

<form method="POST" action="{{ route('generate') }}">
@csrf
<label>GA4 Property</label>
<select name="ga4_property_id">
<option value="">— None / Skip —</option>
@foreach($properties as $p)
<option value="{{ $p['id'] }}" @if($conn->ga4_property_id === $p['id']) selected @endif>{{ $p['name'] }}</option>
@endforeach
</select>
@if(empty($properties))
<p class="warn">No GA4 properties found for this account.</p>
@endif

<label>Search Console Site</label>
<select name="gsc_site_url">
<option value="">— None / Skip —</option>
@foreach($sites as $s)
<option value="{{ $s['url'] }}" @if($conn->gsc_site_url === $s['url']) selected @endif>{{ $s['url'] }}</option>
@endforeach
</select>
@if(empty($sites))
<p class="warn">No Search Console sites found for this account.</p>
@endif

<button type="submit">Generate my report →</button>
</form>
</body>
</html>
