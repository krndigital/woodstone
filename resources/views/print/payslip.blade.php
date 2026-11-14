@extends('print.layout')
@section('title', 'Payslip '.$p->month)
@php $rs = fn ($v) => number_format((float) $v, 2); @endphp
@section('body')
    <h1>{{ $settings['restaurant_name'] }}</h1>
    <div class="c b">PAYSLIP · {{ \Carbon\Carbon::parse($p->month.'-01')->format('F Y') }}</div>
    <hr>
    <table>
        <tr><td>Name</td><td class="r b">{{ $p->staff->name }}</td></tr>
        <tr><td>Position</td><td class="r">{{ $p->staff->position }}</td></tr>
        <tr><td>Salary</td><td class="r">{{ $rs($p->base_salary) }} / {{ $p->staff->salary_type === 'daily' ? 'day' : 'month' }}</td></tr>
    </table>
    <hr>
    <table>
        <tr><td>Working days</td><td class="r">{{ $p->working_days }}</td></tr>
        <tr><td>Present (late)</td><td class="r">{{ $p->present_days }} ({{ $p->late_days }})</td></tr>
        <tr><td>Leave</td><td class="r">{{ $p->leave_days }}</td></tr>
        <tr><td>Absent</td><td class="r">{{ $p->absent_days }}</td></tr>
    </table>
    <hr>
    <table>
        <tr><td>Earned</td><td class="r">{{ $rs($p->earned) }}</td></tr>
        @if ((float) $p->bonus > 0)<tr><td>Bonus</td><td class="r">{{ $rs($p->bonus) }}</td></tr>@endif
        <tr><td>Advance deducted</td><td class="r">-{{ $rs($p->advance_deduction) }}</td></tr>
        @if ((float) $p->other_deduction > 0)<tr><td>Other deduction</td><td class="r">-{{ $rs($p->other_deduction) }}</td></tr>@endif
        <tr class="big b"><td>NET PAY</td><td class="r">Rs {{ $rs($p->net) }}</td></tr>
    </table>
    <hr>
    <div>{{ $p->status === 'paid' ? 'Paid on '.$p->paid_at?->timezone('Asia/Kathmandu')->format('d M Y') : 'DRAFT — not paid yet' }}</div>
    <div style="margin-top:26px; border-top:1px solid #000; width:60%">Staff signature</div>
@endsection
