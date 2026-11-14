<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement · {{ $customer->name }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font: 11px/1.4 Inter, system-ui, Arial, sans-serif; color: #111; margin: 0; }
        h1 { font-size: 18px; margin: 0; } .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background: #f3f3f3; } .n { text-align: right; font-variant-numeric: tabular-nums; }
        .bar { display: flex; justify-content: space-between; border-bottom: 2px solid #e8590c; padding-bottom: 6px; }
        .noprint { margin: 10px 0; } @media print { .noprint { display: none; } }
    </style>
</head>
<body>
@php $rs = fn ($v) => number_format((float) $v, 2); @endphp
<div class="noprint"><button type="button" id="print-btn">Save as PDF / Print</button></div>
<div class="bar">
    <div><h1>Customer statement</h1><div class="muted">{{ $settings['restaurant_name'] }} · {{ $settings['address'] }}</div></div>
    <div class="muted" style="text-align:right"><b>{{ $customer->name }}</b><br>{{ $customer->phone }}<br>{{ \App\Support\BsDate::long(now()) }} BS</div>
</div>
<table>
    <thead><tr><th>Date (BS)</th><th>What</th><th>Ref</th><th>Note</th><th class="n">Amount</th><th class="n">Balance</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr><td>{{ \App\Support\BsDate::format($r['date']) }}</td><td>{{ $r['what'] }}</td><td>{{ $r['ref'] }}</td><td>{{ $r['note'] }}</td><td class="n">{{ $rs($r['amount']) }}</td><td class="n">{{ $rs($r['balance']) }}</td></tr>
    @empty
        <tr><td colspan="6" class="muted">No entries.</td></tr>
    @endforelse
    </tbody>
</table>
<p><b>Due now: Rs {{ number_format($summary['balance'] / 100, 2) }}</b> · 0–30 days {{ number_format($summary['age_0_30'] / 100, 2) }} · 31–60 days {{ number_format($summary['age_31_60'] / 100, 2) }} · 60+ days {{ number_format($summary['age_60_plus'] / 100, 2) }}</p>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    document.getElementById('print-btn').addEventListener('click', () => window.print());
</script>
</body>
</html>
