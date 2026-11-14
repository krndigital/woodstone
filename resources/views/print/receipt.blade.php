@extends('print.layout')
@section('title', 'Receipt '.$entry->id)
@php $rs = fn ($v) => number_format((float) $v, 2); @endphp
@section('body')
    <h1>{{ $settings['restaurant_name'] }}</h1>
    <div class="c">{{ $settings['address'] }}</div>
    <hr>
    <div class="c b">PAYMENT RECEIPT</div>
    <table>
        <tr><td>Receipt no</td><td class="r b">R-{{ str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT) }}</td></tr>
        <tr><td>Date (BS)</td><td class="r">{{ \App\Support\BsDate::long($entry->created_at) }}</td></tr>
        <tr><td>Date (AD)</td><td class="r">{{ $entry->created_at->timezone('Asia/Kathmandu')->format('d M Y, h:i A') }}</td></tr>
        <tr><td>Customer</td><td class="r">{{ $customer->name }}</td></tr>
        @if ($customer->phone)<tr><td>Phone</td><td class="r">{{ $customer->phone }}</td></tr>@endif
        <tr><td>Paid by</td><td class="r">{{ $entry->method?->label() }}{{ $entry->reference ? ' #'.$entry->reference : '' }}</td></tr>
    </table>
    <hr>
    <table>
        <tr><td>Due before</td><td class="r">{{ $rs($before) }}</td></tr>
        <tr class="big b"><td>RECEIVED</td><td class="r">Rs {{ $rs(abs((float) $entry->amount)) }}</td></tr>
        <tr class="b"><td>Due now</td><td class="r">{{ $rs($after) }}</td></tr>
    </table>
    <hr>
    <div class="muted">Received by {{ $entry->creator->name }}</div>
    <div style="margin-top:22px; border-top:1px solid #000; width:60%">Signature</div>
@endsection
