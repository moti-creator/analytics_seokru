<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow, noarchive">
<title>Ask — SEOKRU Analytics</title>
<style>
body{font-family:system-ui,sans-serif;max-width:720px;margin:60px auto;padding:20px;color:#222;line-height:1.6}
h1{margin-bottom:.2em}
.sub{color:#666;margin-bottom:2em}
label{display:block;margin:1em 0 .3em;font-weight:600;font-size:.95rem}
select,textarea,button{width:100%;padding:12px;font-size:1rem;border-radius:6px;border:1px solid #ccc;box-sizing:border-box;font-family:inherit}
textarea{min-height:90px;resize:vertical}
button{background:#1a73e8;color:#fff;border:none;font-weight:600;cursor:pointer;margin-top:1.2em;font-size:1.05rem}
button:hover{background:#1557b0}
button:disabled{background:#888;cursor:wait}
.muted{color:#888;font-size:.88rem}
.examples{background:#f5f8ff;border-left:3px solid #1a73e8;padding:12px 16px;margin:1em 0;border-radius:0 6px 6px 0}
.examples p{margin:.3em 0;font-size:.9rem;color:#444;cursor:pointer}
.examples p:hover{color:#1a73e8;text-decoration:underline}
</style>
</head>
<body>
<h1>Ask anything</h1>
<p class="sub">Connected as <strong>{{ $conn->email }}</strong>. Describe the report you want in plain English.</p>

<form method="POST" action="{{ route('ask.run') }}" onsubmit="document.getElementById('sub').disabled=true;document.getElementById('sub').textContent='Thinking... (up to 60 sec)'">
@csrf

<label>GA4 Property</label>
<select name="ga4_property_id">
<option value="">— None (GSC only) —</option>
@foreach($properties as $p)
<option value="{{ $p['id'] }}" @if($conn->ga4_property_id === $p['id']) selected @endif>{{ $p['name'] }}</option>
@endforeach
</select>

<label>Search Console Site</label>
<select name="gsc_site_url">
<option value="">— None (GA4 only) —</option>
@foreach($sites as $s)
<option value="{{ $s['url'] }}" @if($conn->gsc_site_url === $s['url']) selected @endif>{{ $s['url'] }}</option>
@endforeach
</select>

<label>Your question</label>
<textarea name="prompt" required placeholder="e.g. Top landing pages last 7 days, sorted by sessions, with conversion rate">{{ session('pending_prompt', '') }}</textarea>
@php session()->forget('pending_prompt'); @endphp

<div class="examples">
<p onclick="fillPrompt(this)">Top 20 landing pages last 30 days with sessions and conversion rate</p>
<p onclick="fillPrompt(this)">Search queries ranked 4-20 with the most impressions last month</p>
<p onclick="fillPrompt(this)">Compare mobile vs desktop users last 7 days vs previous 7 days</p>
<p onclick="fillPrompt(this)">Which pages had traffic drop more than 30% vs last month</p>
<p onclick="fillPrompt(this)">Top countries sending organic traffic last 28 days</p>
</div>

<button id="sub" type="submit">Generate report</button>
</form>

<script>
function fillPrompt(el){ document.querySelector('textarea[name=prompt]').value = el.textContent.trim(); }
</script>
</body>
</html>
