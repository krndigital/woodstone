@extends('print.layout')
@section('title', 'PO '.$po->number)
@section('body')
    <h1>PURCHASE ORDER</h1>
    <div class="c b">{{ $settings['restaurant_name'] }}</div>
    <div class="c">{{ $settings['address'] }}</div>
    @if ($settings['phone'])<div class="c">{{ $settings['phone'] }}</div>@endif
    <hr>
    <table>
        <tr><td>PO no</td><td class="r b">{{ $po->number }}</td></tr>
        <tr><td>To</td><td class="r">{{ $po->supplier->name }}</td></tr>
        <tr><td>Date</td><td class="r">{{ $po->created_at?->timezone('Asia/Kathmandu')->format('d M Y') }}</td></tr>
        @if ($po->expected_on)<tr><td>Needed by</td><td class="r">{{ $po->expected_on->format('d M Y') }}</td></tr>@endif
    </table>
    <hr>
    <table>
        @foreach ($po->lines as $line)
            <tr>
                <td>{{ $line->stockItem->name }}</td>
                <td class="r b">{{ rtrim(rtrim($line->quantity, '0'), '.') }} {{ $line->stockItem->purchase_unit ?? $line->stockItem->unit }}</td>
            </tr>
        @endforeach
    </table>
    @if ($po->note)<hr><div>{{ $po->note }}</div>@endif
    <hr>
@endsection
