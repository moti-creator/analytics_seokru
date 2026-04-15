<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $report->title ?? 'Report' }}</title>
<style>
body{font-family:system-ui,Arial,sans-serif;max-width:820px;margin:40px auto;padding:20px;color:#222;line-height:1.6}
h1{border-bottom:2px solid #1a73e8;padding-bottom:.3em;margin-bottom:.2em}
h2{color:#1a73e8;margin-top:2em;font-size:1.25rem}
table{border-collapse:collapse;margin:1em 0;width:100%}
th,td{border:1px solid #ddd;padding:8px 12px;text-align:left;font-size:.92rem}
th{background:#f5f8ff}
.meta{color:#666;font-size:.9rem;margin-bottom:1.5em}
.btn{display:inline-block;background:#1a73e8;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;margin:.5em 0 1.5em}
.btn-sec{background:#fff;color:#1a73e8;border:1px solid #1a73e8;margin-left:8px}
</style></head>
<body>
<h1>{{ $report->title ?? 'Analytics Report' }}</h1>
<p class="meta">
{{ $report->connection->email }} ·
Generated {{ $report->created_at->format('M j, Y H:i') }}
</p>

<a href="{{ route('report.pdf', $report->id) }}" class="btn">Download PDF</a>
<a href="/" class="btn btn-sec">← Run another report</a>

{!! $report->narrative !!}
</body>
</html>
