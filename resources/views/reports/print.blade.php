<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['title'] }} · {{ $period->label() }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font: 11px/1.4 Inter, system-ui, Arial, sans-serif; color: #111; margin: 0; }
        h1 { font-size: 18px; margin: 0; } .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #ddd; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; font-weight: 600; }
        .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tfoot td { font-weight: 700; border-top: 2px solid #111; }
        .bar { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #e8590c; padding-bottom: 6px; }
        .noprint { margin: 10px 0; } @media print { .noprint { display: none; } }
    </style>
</head>
<body>
@php
    $fmt = function ($v, $type) {
        if (! is_int($v)) return e((string) ($v ?? ''));
        return match ($type) {
            'money' => number_format($v / 100, 2),
            'percent' => number_format($v / 100, 1).'%',
            'qty' => rtrim(rtrim(number_format($v / 1000, 3, '.', ','), '0'), '.'),
            default => number_format($v),
        };
    };
@endphp
<div class="noprint"><button onclick="window.print()">Save as PDF / Print</button></div>
<div class="bar">
    <div><h1>{{ $meta['title'] }}</h1><div class="muted">{{ $restaurant }}</div></div>
    <div class="muted">{{ $period->label() }}<br>Printed {{ now()->format('d M Y, h:i A') }}</div>
</div>
<table>
    <thead><tr>@foreach ($data['columns'] as $c)<th class="{{ $c['type'] === 'text' ? '' : 'n' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($data['rows'] as $row)
        <tr>@foreach ($data['columns'] as $c)<td class="{{ $c['type'] === 'text' ? '' : 'n' }}">{!! $fmt($row[$c['key']] ?? null, $c['type']) !!}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($data['columns']) }}" class="muted">No data for this period.</td></tr>
    @endforelse
    </tbody>
    @if ($data['totals'])
        <tfoot><tr>@foreach ($data['columns'] as $c)<td class="{{ $c['type'] === 'text' ? '' : 'n' }}">{!! array_key_exists($c['key'], $data['totals']) ? $fmt($data['totals'][$c['key']], $c['type']) : '' !!}</td>@endforeach</tr></tfoot>
    @endif
</table>
@foreach ($data['notes'] as $note)<p class="muted">{{ $note }}</p>@endforeach
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 200));</script>
</body>
</html>
