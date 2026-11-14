@extends('print.layout')
@section('title', 'KOT '.$kot->number)
@section('body')
    <h1>KOT #{{ $kot->number }}</h1>
    <div class="c b">{{ strtoupper($kot->station->name) }}</div>
    @if ($reprint)<div class="c"><span class="tag">REPRINT</span></div>@endif
    <hr>
    <div class="big b">{{ $kot->order->label() }}</div>
    <div class="muted">Order #{{ $kot->order->number }} · {{ $kot->order->guests }} guests · {{ $kot->creator->name }}</div>
    <div class="muted">{{ $kot->created_at?->timezone('Asia/Kathmandu')->format('d M Y, h:i A') }}</div>
    <hr>
    <table>
        @foreach ($kot->items as $line)
            <tr>
                <td class="b big" style="width: 12mm">{{ $line->quantity }} ×</td>
                <td class="big">
                    {{ $line->name }}
                    @if ($line->status->value === 'void') <span class="tag">VOID</span>@endif
                    @foreach ($line->modifiers ?? [] as $mod)<div class="muted">+ {{ $mod['name'] }}</div>@endforeach
                    @if ($line->note)<div class="muted">» {{ $line->note }}</div>@endif
                </td>
            </tr>
        @endforeach
    </table>
    @if ($kot->order->note)<hr><div>Note: {{ $kot->order->note }}</div>@endif
    <hr>
@endsection
