<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\Payroll;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffActionRequest;
use App\Models\Account;
use App\Models\Payslip;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monthly salary sheet and payslips (FR-12.4). Behind `can:staff.manage`.
 */
class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $request->string('month')->toString()) === 1 ? $request->string('month')->toString() : now()->format('Y-m');

        return Inertia::render('staff/Payroll', [
            'month' => $month,
            'payslips' => Payslip::query()->with('staff:id,name,position,salary_type')->where('month', $month)->orderBy('id')->get()->map(fn (Payslip $p): array => [
                'id' => $p->id, 'staff' => $p->staff->name, 'position' => $p->staff->position, 'salary_type' => $p->staff->salary_type,
                'working_days' => $p->working_days, 'present_days' => $p->present_days, 'late_days' => $p->late_days,
                'leave_days' => $p->leave_days, 'absent_days' => $p->absent_days, 'base_salary' => $p->base_salary,
                'earned' => $p->earned, 'advance_deduction' => $p->advance_deduction, 'other_deduction' => $p->other_deduction,
                'bonus' => $p->bonus, 'net' => $p->net, 'status' => $p->status,
            ])->values()->all(),
            'accounts' => Account::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])->toArray(),
        ]);
    }

    public function generate(StaffActionRequest $request, Payroll $payroll): RedirectResponse
    {
        $count = $payroll->generate($request->string('month')->toString(), $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count payslips ready.', ['count' => $count])]);

        return to_route('staff.payroll', ['month' => $request->string('month')->toString()]);
    }

    public function update(StaffActionRequest $request, Payslip $payslip, Payroll $payroll): RedirectResponse
    {
        $payroll->adjust($payslip, $request->string('bonus')->toString(), $request->string('other_deduction')->toString(), $request->actor());

        return back();
    }

    public function pay(StaffActionRequest $request, Payslip $payslip, Payroll $payroll): RedirectResponse
    {
        $payroll->pay($payslip, Account::query()->findOrFail($request->integer('account_id')), $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Salary paid.')]);

        return back();
    }

    public function print(Payslip $payslip): View
    {
        $payslip->load('staff');

        return view('print.payslip', ['p' => $payslip, 'settings' => Settings::all()]);
    }
}
