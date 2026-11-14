@extends('print.layout')
@section('title', 'Bill '.$bill->number)
@php
    $rs = fn ($v) => number_format((float) $v, 2);
    $paidNow = $bill->payments->filter(fn ($p) => $p->method->value !== 'due');
    $onDue = $bill->payments->filter(fn ($p) => $p->method->value === 'due');
    $at = $bill->created_at?->timezone('Asia/Kathmandu');
@endphp
@section('body')
    <h1>{{ $settings['restaurant_name'] }}</h1>
    <div class="c">{{ $settings['address'] }}</div>
    @if ($settings['phone'])<div class="c">Phone: {{ $settings['phone'] }}</div>@endif
    @if ($settings['pan'])<div class="c">PAN: {{ $settings['pan'] }}</div>@endif
    @if ($copy)<div class="c" style="margin-top:4px"><span class="tag">COPY</span></div>@endif
    <hr>
    <div class="c b">{{ (float) $bill->vat_percent > 0 ? 'TAX INVOICE' : 'INVOICE' }}</div>
    @if ($bill->order->token)<div class="c" style="font-size:28px;font-weight:bold;margin:4px 0">TOKEN {{ $bill->order->token }}</div>@endif
    <table>
        <tr><td>Bill no</td><td class="r b">{{ $bill->number }}</td></tr>
        <tr><td>Date</td><td class="r">{{ $at?->format('d M Y, h:i A') }}</td></tr>
        <tr><td>{{ $bill->order->type->value === 'dine_in' ? 'Table' : 'Order' }}</td><td class="r">{{ $bill->order->label() }}</td></tr>
        <tr><td>Waiter</td><td class="r">{{ $bill->order->waiter->name }}</td></tr>
        <tr><td>Cashier</td><td class="r">{{ $bill->cashier->name }}</td></tr>
        @if ($bill->order->customer_name)<tr><td>Customer</td><td class="r">{{ $bill->order->customer_name }}</td></tr>@endif
    </table>
    <hr>
    <table>
        @foreach ($bill->order->items->filter(fn ($l) => $l->status->value !== 'void') as $line)
            <tr>
                <td>{{ $line->quantity }} × {{ $line->name }}
                    @foreach ($line->modifiers ?? [] as $mod)<div class="muted">+ {{ $mod['name'] }}</div>@endforeach
                </td>
                <td class="r">{{ $rs($line->line_total) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Subtotal</td><td class="r">{{ $rs($bill->subtotal) }}</td></tr>
        @if ((float) $bill->discount_amount > 0)
            <tr><td>Discount{{ $bill->discount_type === 'percent' ? ' ('.rtrim(rtrim($bill->discount_value, '0'), '.').'%)' : '' }}</td><td class="r">-{{ $rs($bill->discount_amount) }}</td></tr>
        @endif
        <tr><td>Service charge ({{ rtrim(rtrim($bill->service_percent, '0'), '.') ?: '0' }}%)</td><td class="r">{{ $rs($bill->service_amount) }}</td></tr>
        <tr><td>VAT ({{ rtrim(rtrim($bill->vat_percent, '0'), '.') ?: '0' }}%)</td><td class="r">{{ $rs($bill->vat_amount) }}</td></tr>
        <tr class="big b"><td>TOTAL</td><td class="r">Rs {{ $rs($bill->total) }}</td></tr>
    </table>
    <hr>
    @if ($paidNow->isNotEmpty())
        <div class="b">PAID NOW</div>
        <table>
            @foreach ($paidNow as $p)
                <tr><td>{{ $p->payer ? $p->payer.' · ' : '' }}{{ $p->method->label() }}{{ $p->reference ? ' #'.$p->reference : '' }}</td><td class="r">{{ $rs($p->amount) }}</td></tr>
            @endforeach
        </table>
    @endif
    @if ($onDue->isNotEmpty())
        <div class="b" style="margin-top:4px">ON DUE</div>
        <table>
            @foreach ($onDue as $p)
                <tr><td>{{ $p->payer ? $p->payer.' · ' : '' }}{{ $p->customer?->name }}</td><td class="r">{{ $rs($p->amount) }}</td></tr>
            @endforeach
        </table>
    @endif
    @foreach ($dueAccounts as $acc)
        <hr>
        <div class="b">DUE ACCOUNT · {{ $acc['name'] }}</div>
        @if ($acc['phone'])<div class="muted">{{ $acc['phone'] }}</div>@endif
        <table>
            <tr><td>Previous due</td><td class="r">{{ $rs($acc['before']) }}</td></tr>
            <tr><td>This bill</td><td class="r">{{ $rs($acc['this_bill']) }}</td></tr>
            <tr class="b"><td>Total due</td><td class="r">{{ $rs($acc['after']) }}</td></tr>
        </table>
        <div style="margin-top:22px; border-top:1px solid #000; width:60%">Customer signature</div>
    @endforeach
    <hr>
    @if ($settings['bill_footer'])<div class="c">{{ $settings['bill_footer'] }}</div>@endif
@endsection
