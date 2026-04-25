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
*{box-sizing:border-box}
body{font-family:system-ui,sans-serif;max-width:860px;margin:30px auto;padding:20px;color:#222;line-height:1.55}

/* topbar */
.topbar{display:flex;justify-content:space-between;align-items:center;padding:10px 0 14px;border-bottom:1px solid #eee;margin-bottom:1.5em}
.brand{font-weight:700;color:#1a73e8;font-size:1.1rem;text-decoration:none}
.email{color:#888;font-size:.85rem}
.email a{color:#888}

h1{margin:.2em 0 .2em;font-size:1.7rem}
.sub{color:#666;margin-bottom:1.2em;font-size:.95rem}

/* property selector — same colored cards as landing */
.prop-switch{display:flex;gap:12px;align-items:stretch;margin-bottom:1.5em;flex-wrap:wrap}
.prop-pick{flex:1;min-width:240px;display:flex;flex-direction:column;gap:6px;padding:12px 16px;border-radius:10px;border:2px solid transparent;transition:all .2s;cursor:pointer}
.prop-pick.ga4{background:#eef3ff;border-color:#c7d7ff}
.prop-pick.ga4:hover{background:#dce7ff;border-color:#1a73e8;box-shadow:0 4px 14px rgba(26,115,232,.18)}
.prop-pick.gsc{background:#f3eaff;border-color:#d8b4ff}
.prop-pick.gsc:hover{background:#ebdbff;border-color:#7c3aed;box-shadow:0 4px 14px rgba(124,58,237,.18)}
.prop-lbl{font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;display:flex;align-items:center;gap:6px}
.prop-pick.ga4 .prop-lbl{color:#1a73e8}
.prop-pick.gsc .prop-lbl{color:#7c3aed}
.prop-lbl .dot{width:8px;height:8px;border-radius:50%}
.prop-pick.ga4 .dot{background:#1a73e8}
.prop-pick.gsc .dot{background:#7c3aed}
.prop-pick select{padding:8px 10px;border:1px solid rgba(0,0,0,.1);border-radius:6px;font-size:.9rem;background:#fff;cursor:pointer;font-family:inherit;width:100%}
.prop-pick select:focus{outline:2px solid currentColor;outline-offset:1px}

/* hero ask box */
.ask-hero{background:linear-gradient(135deg,#f5f8ff 0%,#eef3ff 100%);border:1px solid #d8e4ff;border-radius:14px;padding:24px;margin-bottom:1.5em}
.ask-hero label{display:block;font-weight:600;margin-bottom:8px;font-size:.95rem;color:#1a73e8}
.ask-hero textarea{width:100%;min-height:110px;padding:14px;font-size:1.02rem;border:1px solid #cfd8e3;border-radius:10px;font-family:inherit;resize:vertical;background:#fff}
.ask-hero textarea:focus{outline:none;border-color:#1a73e8;box-shadow:0 0 0 3px rgba(26,115,232,.12)}
.ask-hero .examples{margin-top:10px}
.ask-hero .examples-label{font-size:.78rem;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;font-weight:600}
.ask-hero .ex-chip{display:inline-block;background:#fff;border:1px solid #d8e4ff;color:#1a73e8;padding:6px 12px;border-radius:20px;font-size:.85rem;cursor:pointer;margin:3px 4px 3px 0;transition:all .2s}
.ask-hero .ex-chip:hover{background:#1a73e8;color:#fff;border-color:#1a73e8}
.ask-hero .row{display:flex;justify-content:space-between;align-items:center;margin-top:14px;gap:10px;flex-wrap:wrap}
.ask-hero .hint{color:#777;font-size:.85rem}
.ask-hero button.primary{background:#1a73e8;color:#fff;border:0;padding:12px 28px;border-radius:10px;font-size:1rem;cursor:pointer;font-weight:600;box-shadow:0 4px 14px rgba(26,115,232,.25);transition:all .2s}
.ask-hero button.primary:hover{background:#1557b8;transform:translateY(-1px)}
.ask-hero button.primary:disabled{background:#888;cursor:wait;transform:none}

/* save row */
.save-row{display:flex;gap:8px;margin-top:10px;align-items:center}
.save-row input{flex:1;padding:10px;font-size:.9rem;border:1px solid #ddd;border-radius:6px;font-family:inherit}
.save-row button{padding:10px 18px;background:#fff;color:#1a73e8;border:1px solid #1a73e8;cursor:pointer;font-size:.88rem;border-radius:6px;font-weight:600;white-space:nowrap}
.save-row button:hover{background:#1a73e8;color:#fff}

.flash{background:#e6f4ea;border-left:3px solid #1e8e3e;padding:10px 14px;margin:1em 0;border-radius:0 6px 6px 0;font-size:.9rem}

/* sections below */
.section{background:#fafafa;border:1px solid #eee;border-radius:8px;padding:14px 18px;margin-bottom:1.2em}
.section h3{margin:0 0 .6em;font-size:.85rem;color:#666;text-transform:uppercase;letter-spacing:.05em;font-weight:700}

.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip{background:#f0f7ff;color:#1a73e8;border:1px solid #d8e4ff;padding:6px 12px;border-radius:20px;font-size:.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.chip:hover{background:#1a73e8;color:#fff}
.chip .x{color:#c33;font-weight:700;margin-left:4px}
.chip .x:hover{color:#fff}

.recent-item{display:grid;grid-template-columns:1fr auto auto;align-items:center;padding:10px 0;border-bottom:1px solid #eee;gap:10px}
.recent-item:last-child{border-bottom:0}
.recent-item a{color:#1a73e8;text-decoration:none;font-size:.92rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.recent-item a:hover{text-decoration:underline}
.recent-item .date{color:#999;font-size:.8rem;white-space:nowrap}
.recent-item .reuse{color:#666;font-size:.82rem;cursor:pointer;border:1px solid #ddd;padding:4px 12px;border-radius:4px;background:#fff;white-space:nowrap}
.recent-item .reuse:hover{border-color:#1a73e8;color:#1a73e8}
</style>
</head>
<body>

<div class="topbar">
<a href="/" class="brand">SEOKRU Analytics</a>
<span class="email">{{ $conn->email }} · <a href="/">← back</a></span>
</div>

<h1>Ask anything</h1>
<p class="sub">Plain-English question → GA4 + Search Console answer in &lt;60 seconds.</p>

@if(session('status'))
<div class="flash">{{ session('status') }}</div>
@endif

{{-- ====== Property selector (top, prominent) ====== --}}
<form method="POST" action="{{ route('ask.run') }}" id="askForm" onsubmit="document.getElementById('sub').disabled=true;document.getElementById('sub').textContent='Thinking... (up to 60 sec)'">
@csrf

<div class="prop-switch">
<label class="prop-pick ga4">
<span class="prop-lbl"><span class="dot"></span>Google Analytics 4</span>
<select name="ga4_property_id">
<option value="">— None (GSC only) —</option>
@foreach($properties as $p)
<option value="{{ $p['id'] }}" @if($conn->ga4_property_id === $p['id']) selected @endif>{{ $p['name'] }}</option>
@endforeach
</select>
</label>
<label class="prop-pick gsc">
<span class="prop-lbl"><span class="dot"></span>Search Console</span>
<select name="gsc_site_url">
<option value="">— None (GA4 only) —</option>
@foreach($sites as $s)
<option value="{{ $s['url'] }}" @if($conn->gsc_site_url === $s['url']) selected @endif>{{ $s['url'] }}</option>
@endforeach
</select>
</label>
</div>

{{-- ====== Hero textarea (BEFORE recent questions) ====== --}}
<div class="ask-hero">
<label for="promptField">Your question</label>
<textarea name="prompt" id="promptField" required placeholder="e.g. Top 5 pages with the biggest improvement in clicks last 28 days">{{ session('pending_prompt', '') }}</textarea>
@php session()->forget('pending_prompt'); @endphp

<div class="examples">
<div class="examples-label">Try one of these:</div>
<span class="ex-chip" onclick="fillPromptText(this)">Top 20 landing pages last 30 days with conversion rate</span>
<span class="ex-chip" onclick="fillPromptText(this)">Search queries ranked 4-20 with the most impressions</span>
<span class="ex-chip" onclick="fillPromptText(this)">Compare mobile vs desktop users last 7 days vs previous</span>
<span class="ex-chip" onclick="fillPromptText(this)">Pages with traffic drop more than 30% vs last month</span>
<span class="ex-chip" onclick="fillPromptText(this)">Top countries sending organic traffic last 28 days</span>
</div>

<div class="row">
<span class="hint">Agent fetches GA4 + Search Console data, computes math, writes the answer.</span>
<button id="sub" class="primary" type="submit">Generate report →</button>
</div>
</div>
</form>

{{-- ====== Save current as a saved query ====== --}}
<form method="post" action="{{ route('ask.save') }}" class="save-row">
@csrf
<input type="hidden" name="prompt" id="savePrompt">
<input type="text" name="label" maxlength="120" placeholder="Save current question as... (e.g. 'Weekly mobile check')">
<button type="submit">★ Save</button>
</form>

{{-- ====== Saved queries (chips) ====== --}}
@if(count($saved))
<div class="section" style="margin-top:1.5em">
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

{{-- ====== Recent questions ====== --}}
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
    window.scrollTo({top:0,behavior:'smooth'});
}
function fillFromChip(el){
    document.getElementById('promptField').value = el.dataset.prompt;
    syncSave();
    document.getElementById('promptField').focus();
    window.scrollTo({top:0,behavior:'smooth'});
}
function syncSave(){
    document.getElementById('savePrompt').value = document.getElementById('promptField').value;
}
document.getElementById('promptField').addEventListener('input', syncSave);
syncSave();
</script>
@include('partials.footer')
</body>
</html>
