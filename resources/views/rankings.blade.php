<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Keyword Rankings — {{ $conn->gsc_site_url }}</title>
<style>
*{box-sizing:border-box}
body{font-family:system-ui,sans-serif;margin:0;padding:20px;color:#222;line-height:1.4}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eee;margin-bottom:1em;flex-wrap:wrap;gap:10px}
.topbar .brand{font-weight:700;color:#1a73e8;font-size:1.1rem}
.topbar a{color:#555;font-size:.85rem;text-decoration:none;margin-right:10px}
.topbar a:hover{color:#1a73e8}
h1{font-size:1.35rem;margin:.2em 0}
.sub{color:#666;font-size:.9rem;margin:0 0 1em}
.controls{display:flex;gap:10px;flex-wrap:wrap;align-items:end;background:#f8fafc;border:1px solid #e3e8ee;border-radius:10px;padding:12px 16px;margin-bottom:14px}
.controls label{display:flex;flex-direction:column;gap:3px;font-size:.78rem;color:#555}
.controls input,.controls select{padding:6px 10px;border:1px solid #cfd8e3;border-radius:6px;font-size:.9rem;min-width:80px}
.controls .search{min-width:220px}
.controls button{background:#1a73e8;color:#fff;border:0;padding:8px 16px;border-radius:6px;font-size:.88rem;cursor:pointer;font-weight:500}
.controls button:hover{background:#1557b8}
.controls .csv{background:#fff;color:#1a73e8;border:1px solid #1a73e8}
.controls .csv:hover{background:#f5f8ff}
.meta-row{font-size:.82rem;color:#777;margin-bottom:8px}

/* Pivot table */
.wrap{overflow:auto;max-height:calc(100vh - 230px);border:1px solid #e3e8ee;border-radius:8px;background:#fff}
table.pivot{border-collapse:collapse;font-size:.82rem;width:max-content;min-width:100%}
table.pivot thead th{position:sticky;top:0;background:#f8fafc;z-index:3;border-bottom:2px solid #cfd8e3;padding:8px 10px;font-weight:600;color:#555;text-align:center;white-space:nowrap}
table.pivot thead th.q{text-align:left;left:0;z-index:4;min-width:260px;max-width:360px;border-right:2px solid #cfd8e3}
table.pivot thead th.imps,table.pivot thead th.clicks{right:auto;z-index:3;min-width:80px}
table.pivot tbody td{padding:6px 8px;border-bottom:1px solid #eef1f5;text-align:center;font-variant-numeric:tabular-nums}
table.pivot tbody td.q{position:sticky;left:0;background:#fff;text-align:left;font-weight:500;color:#222;z-index:2;border-right:2px solid #cfd8e3;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
table.pivot tbody tr:hover td.q{background:#f5f8ff}
table.pivot tbody tr:hover td{background:#fafcff}
table.pivot tbody td.num{color:#555;min-width:60px}
table.pivot tbody td.empty{color:#ccc}

/* Heatmap coloring on position cells */
.p1{background:#065f46;color:#fff}
.p2{background:#10b981;color:#fff}
.p3{background:#34d399;color:#064e3b}
.p4{background:#a7f3d0;color:#064e3b}
.p5{background:#fef3c7;color:#78350f}
.p6{background:#fde68a;color:#78350f}
.p7{background:#fbbf24;color:#78350f}
.p8{background:#fb923c;color:#7c2d12}
.p9{background:#ef4444;color:#fff}
.p10{background:#7f1d1d;color:#fff}

.summary{background:#f5f8ff;border:1px solid #d8e4ff;border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:.85rem;color:#444;display:flex;gap:18px;flex-wrap:wrap}
.summary b{color:#1a73e8}
.legend{display:flex;gap:4px;align-items:center;font-size:.72rem;color:#666;margin-top:6px;flex-wrap:wrap}
.legend span{padding:2px 6px;border-radius:3px;font-weight:600;font-size:.7rem}

.empty-state{text-align:center;padding:40px 20px;color:#777}
.empty-state h3{color:#444;margin-bottom:.5em}
</style>
</head>
<body>

<div class="topbar">
<div class="brand"><a href="/" style="color:inherit;text-decoration:none">← SEOKRU Analytics</a></div>
<div>
<span style="color:#666;font-size:.85rem">{{ $conn->email }}</span>
</div>
</div>

<h1>Keyword Rankings</h1>
<p class="sub">{{ $conn->gsc_site_url }} · {{ $start }} → {{ $end }}</p>

<form method="get" class="controls">
<label>Months back
<input type="number" name="months" value="{{ $months }}" min="1" max="24">
</label>
<label>Min impressions
<input type="number" name="min" value="{{ $minImps }}" min="1" max="1000">
</label>
<label>Top N keywords
<input type="number" name="top" value="{{ $topN }}" min="10" max="200">
</label>
<label>Filter
<input type="text" id="kwFilter" class="search" placeholder="Filter query (live)…" oninput="filterRows(this.value)">
</label>
<button type="submit">Apply</button>
<a class="csv" href="{{ route('rankings.csv', ['months'=>$months,'min'=>$minImps,'top'=>$topN]) }}"><button type="button" class="csv">Download CSV</button></a>
</form>

@php
$rowCount = count($pivot['rows']);
$totalImps = array_sum(array_column($pivot['rows'], 'totalImpressions'));
$totalClicks = array_sum(array_column($pivot['rows'], 'totalClicks'));
@endphp

<div class="summary">
<span><b>{{ number_format($rowCount) }}</b> queries · <b>{{ count($pivot['months']) }}</b> months · <b>{{ number_format($totalImps) }}</b> impressions · <b>{{ number_format($totalClicks) }}</b> clicks</span>
<div class="legend">
Position:
<span class="p1">1-2</span>
<span class="p2">3</span>
<span class="p3">4-5</span>
<span class="p4">6-10</span>
<span class="p5">11-15</span>
<span class="p6">16-20</span>
<span class="p7">21-30</span>
<span class="p8">31-50</span>
<span class="p9">51-80</span>
<span class="p10">81+</span>
</div>
</div>

@if($rowCount === 0)
<div class="empty-state">
<h3>No data</h3>
<p>No queries matched. Try lowering "Min impressions" or increasing "Months back".</p>
</div>
@else
<div class="wrap">
<table class="pivot">
<thead>
<tr>
<th class="q">Query</th>
<th class="imps">Impr.</th>
<th class="clicks">Clicks</th>
@foreach($pivot['months'] as $ym)
<th>{{ $ym }}</th>
@endforeach
</tr>
</thead>
<tbody id="pivotBody">
@foreach($pivot['rows'] as $row)
<tr data-q="{{ strtolower($row['query']) }}">
<td class="q" title="{{ $row['query'] }}">{{ $row['query'] }}</td>
<td class="num">{{ number_format($row['totalImpressions']) }}</td>
<td class="num">{{ number_format($row['totalClicks']) }}</td>
@foreach($pivot['months'] as $ym)
@php
$cell = $row['months'][$ym] ?? null;
$pos = $cell['position'] ?? null;
$cls = 'empty';
if ($pos !== null) {
    if ($pos <= 2) $cls = 'p1';
    elseif ($pos <= 3.5) $cls = 'p2';
    elseif ($pos <= 5.5) $cls = 'p3';
    elseif ($pos <= 10.5) $cls = 'p4';
    elseif ($pos <= 15.5) $cls = 'p5';
    elseif ($pos <= 20.5) $cls = 'p6';
    elseif ($pos <= 30.5) $cls = 'p7';
    elseif ($pos <= 50.5) $cls = 'p8';
    elseif ($pos <= 80.5) $cls = 'p9';
    else $cls = 'p10';
}
$imps = $cell['impressions'] ?? 0;
$clicks = $cell['clicks'] ?? 0;
@endphp
<td class="{{ $cls }}" @if($pos !== null) title="Pos {{ $pos }} · {{ number_format($imps) }} impressions · {{ number_format($clicks) }} clicks" @endif>{{ $pos !== null ? $pos : '—' }}</td>
@endforeach
</tr>
@endforeach
</tbody>
</table>
</div>
@endif

<script>
function filterRows(q){
    q = q.toLowerCase().trim();
    document.querySelectorAll('#pivotBody tr').forEach(tr => {
        tr.style.display = !q || tr.dataset.q.includes(q) ? '' : 'none';
    });
}
</script>

</body>
</html>
