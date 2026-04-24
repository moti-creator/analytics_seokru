<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow, noarchive">
<title>Ask — SEOKRU Analytics</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon-180.png">
<style>
body{font-family:system-ui,sans-serif;max-width:820px;margin:50px auto;padding:20px;color:#222;line-height:1.6}
h1{margin-bottom:.2em}
.sub{color:#666;margin-bottom:1.5em}
label{display:block;margin:1em 0 .3em;font-weight:600;font-size:.95rem}
select,textarea,input[type=text],button{width:100%;padding:12px;font-size:1rem;border-radius:6px;border:1px solid #ccc;box-sizing:border-box;font-family:inherit}
textarea{min-height:100px;resize:vertical}
button.primary{background:#1a73e8;color:#fff;border:none;font-weight:600;cursor:pointer;margin-top:1.2em;font-size:1.05rem}
button.primary:hover{background:#1557b0}
button.primary:disabled{background:#888;cursor:wait}
.muted{color:#888;font-size:.88rem}
.flash{background:#e6f4ea;border-left:3px solid #1e8e3e;padding:10px 14px;margin:1em 0;border-radius:0 6px 6px 0}

.chips{display:flex;flex-wrap:wrap;gap:8px;margin:.5em 0 1em}
.chip{background:#f0f7ff;color:#1a73e8;border:1px solid #d8e4ff;padding:6px 12px;border-radius:20px;font-size:.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.chip:hover{background:#1a73e8;color:#fff}
.chip .x{color:#c33;font-weight:700;margin-left:4px}
.chip .x:hover{color:#fff}

.section{background:#fafafa;border:1px solid #eee;border-radius:8px;padding:14px 18px;margin-bottom:1.2em}
.section h3{margin:0 0 .6em;font-size:.95rem;color:#555;text-transform:uppercase;letter-spacing:.05em}
.recent-item{display:grid;grid-template-columns:1fr auto auto;align-items:center;padding:10px 0;border-bottom:1px solid #eee;gap:10px}
.recent-item:last-child{border-bottom:0}
.recent-item a{color:#1a73e8;text-decoration:none;font-size:.92rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.recent-item a:hover{text-decoration:underline}
.recent-item .date{color:#999;font-size:.8rem;white-space:nowrap}
.recent-item .reuse{color:#666;font-size:.82rem;cursor:pointer;border:1px solid #ddd;padding:4px 12px;border-radius:4px;background:#fff;white-space:nowrap}
.recent-item .reuse:hover{border-color:#1a73e8;color:#1a73e8}

.save-row{display:flex;gap:8px;margin-top:10px}
.save-row input{flex:1}
.save-row button{width:auto;padding:8px 16px;background:#fff;color:#1a73e8;border:1px solid #1a73e8;cursor:pointer;font-size:.9rem}
.save-row button:hover{background:#1a73e8;color:#fff}

.examples{background:#f5f8ff;border-left:3px solid #1a73e8;padding:12px 16px;margin:1em 0;border-radius:0 6px 6px 0}
.examples p{margin:.3em 0;font-size:.9rem;color:#444;cursor:pointer}
.examples p:hover{color:#1a73e8;text-decoration:underline}
</style>
</head>
<body>
<h1>Ask anything</h1>
<p class="sub">Connected as <strong>{{ $conn->email }}</strong>. Describe the report you want in plain English.</p>

@if(session('status'))
<div class="flash">{{ session('status') }}</div>
@endif

@if(count($saved))
<div class="section">
<h3>★ Saved queries</h3>
<div class="chips">
@foreach($saved as $s)
<span class="chip" data-prompt="{{ $s->prompt }}" onclick="fillFromChip(this)">
{{ $s->label }}
<form method="post" action="{{ route('ask.saved.delete', $s) }}" style="display:inline;margin:0" onclick="event.stopPropagation()">@csrf @method('DELETE')
<button type="submit" style="background:none;border:0;padding:0;width:auto;cursor:pointer" class="x" title="Delete">×</button>
</form>
</span>
@endforeach
</div>
</div>
@endif

@if(count($recent))
<div class="section">
<h3>⟲ Recent questions</h3>
@foreach($recent as $r)
<div class="recent-item">
<a href="{{ route('report.show', $r) }}">{{ $r->title }}</a>
<button type="button" class="reuse" onclick="fillPrompt(this)" data-prompt="{{ $r->metrics['prompt'] ?? $r->title }}">Re-run</button>
<span class="date">{{ $r->created_at->diffForHumans() }}</span>
</div>
@endforeach
</div>
@endif

<form method="POST" action="{{ route('ask.run') }}" id="askForm" onsubmit="document.getElementById('sub').disabled=true;document.getElementById('sub').textContent='Thinking... (up to 60 sec)'">
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
<textarea name="prompt" id="promptField" required placeholder="e.g. Top landing pages last 7 days, sorted by sessions, with conversion rate">{{ session('pending_prompt', '') }}</textarea>
@php session()->forget('pending_prompt'); @endphp

<div class="examples">
<p onclick="fillPromptText(this)">Top 20 landing pages last 30 days with sessions and conversion rate</p>
<p onclick="fillPromptText(this)">Search queries ranked 4-20 with the most impressions last month</p>
<p onclick="fillPromptText(this)">Compare mobile vs desktop users last 7 days vs previous 7 days</p>
<p onclick="fillPromptText(this)">Which pages had traffic drop more than 30% vs last month</p>
<p onclick="fillPromptText(this)">Top countries sending organic traffic last 28 days</p>
</div>

<button id="sub" class="primary" type="submit">Generate report</button>
</form>

<form method="post" action="{{ route('ask.save') }}" class="save-row">
@csrf
<input type="hidden" name="prompt" id="savePrompt">
<input type="text" name="label" maxlength="120" placeholder="Save current question as... (e.g. 'Weekly mobile check')">
<button type="submit">★ Save</button>
</form>

<script>
function fillPromptText(el){
    document.getElementById('promptField').value = el.textContent.trim();
    syncSave();
    document.getElementById('promptField').focus();
}
function fillPrompt(btn){
    document.getElementById('promptField').value = btn.dataset.prompt;
    syncSave();
    document.getElementById('promptField').focus();
}
function fillFromChip(el){
    document.getElementById('promptField').value = el.dataset.prompt;
    syncSave();
    document.getElementById('promptField').focus();
}
function syncSave(){
    document.getElementById('savePrompt').value = document.getElementById('promptField').value;
}
document.getElementById('promptField').addEventListener('input', syncSave);
syncSave();
</script>
</body>
</html>
