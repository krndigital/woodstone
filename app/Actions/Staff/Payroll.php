<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Money\RecordExpense;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\ExpenseCategory;
use App\Models\LeaveRequest;
use App\Models\Payslip;
use App\Models\Staff;
use App\Models\StaffAdvance;
use App\Models\User;
use App\Support\Audit;
use App\Support\CashBook;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance summary, advances and monthly salary sheet (FR-12.2, FR-12.4).
 * Monthly staff: salary − (salary ÷ working days × absent days). Daily staff: rate × days worked.
 * Outstanding advances are deducted (up to what was earned). Paying posts a Salary expense.
 */
final class Payroll
{
    public function __construct(private readonly RecordExpense $expenses) {}

    /**
     * @return array{working: int, present: int, late: int, leave: int, absent: int}
     */
    public static function summary(Staff $staff, string $month, ?CarbonImmutable $until = null): array
    {
        $start = CarbonImmutable::parse($month.'-01');
        $end = $start->endOfMonth();
        $until ??= CarbonImmutable::today();
        $last = $end->lessThan($until) ? $end : $until;
        if ($staff->joined_on->greaterThan($start)) {
            $start = CarbonImmutable::parse($staff->joined_on->toDateString());
        }

        $marks = Attendance::query()->where('staff_id', $staff->id)->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])->pluck('status', 'work_date')
            ->mapWithKeys(fn ($status, $date) => [substr((string) $date, 0, 10) => (string) $status])->all();
        $leaveDays = [];
        foreach (LeaveRequest::query()->where('staff_id', $staff->id)->where('status', 'approved')->where('from_date', '<=', $end->toDateString())->where('to_date', '>=', $start->toDateString())->get() as $leave) {
            for ($d = CarbonImmutable::parse($leave->from_date->toDateString()); $d->lessThanOrEqualTo($leave->to_date); $d = $d->addDay()) {
                $leaveDays[$d->toDateString()] = true;
            }
        }

        $s = ['working' => 0, 'present' => 0, 'late' => 0, 'leave' => 0, 'absent' => 0];
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            if ($staff->weekly_off !== null && $d->dayOfWeek === $staff->weekly_off) {
                continue;
            }
            $s['working']++;
            $date = $d->toDateString();
            $mark = $marks[$date] ?? null;
            if ($mark === 'present' || $mark === 'late') {
                $s['present']++;
                $s['late'] += $mark === 'late' ? 1 : 0;
            } elseif ($mark === 'leave' || isset($leaveDays[$date])) {
                $s['leave']++;
            } elseif ($d->lessThanOrEqualTo($last)) {
                $s['absent']++;
            }
        }

        return $s;
    }

    /**
     * Creates or recalculates draft payslips for every active staff member.
     */
    public function generate(string $month, User $by): int
    {
        $count = 0;
        DB::transaction(function () use ($month, $by, &$count): void {
            foreach (Staff::query()->where('is_active', true)->get() as $staff) {
                $existing = Payslip::query()->where('staff_id', $staff->id)->where('month', $month)->first();
                if ($existing !== null && $existing->status === 'paid') {
                    continue;
                }
                $s = self::summary($staff, $month);
                $salary = Money::toPaisa($staff->salary_amount);
                $earned = $staff->salary_type === 'daily'
                    ? $salary * $s['present']
                    : max(0, $salary - ($s['working'] > 0 ? intdiv($salary * $s['absent'], $s['working']) : 0));
                $advances = 0;
                foreach (StaffAdvance::query()->where('staff_id', $staff->id)->whereNull('payslip_id')->pluck('amount') as $a) {
                    $advances += Money::toPaisa((string) $a);
                }
                $deduct = min($advances, $earned);

                $slip = $existing ?? new Payslip;
                $other = $existing !== null ? Money::toPaisa($existing->other_deduction) : 0;
                $bonus = $existing !== null ? Money::toPaisa($existing->bonus) : 0;
                $slip->forceFill([
                    'staff_id' => $staff->id, 'month' => $month,
                    'working_days' => $s['working'], 'present_days' => $s['present'], 'late_days' => $s['late'],
                    'leave_days' => $s['leave'], 'absent_days' => $s['absent'],
                    'base_salary' => Money::fromPaisa($salary), 'earned' => Money::fromPaisa($earned),
                    'advance_deduction' => Money::fromPaisa($deduct), 'other_deduction' => Money::fromPaisa($other),
                    'bonus' => Money::fromPaisa($bonus), 'net' => Money::fromPaisa(max(0, $earned - $deduct - $other + $bonus)),
                    'status' => 'draft',
                ])->save();
                $count++;
            }
            Audit::record('payroll.generated', null, ['month' => $month, 'payslips' => $count], $by);
        });

        return $count;
    }

    public function adjust(Payslip $slip, string $bonus, string $otherDeduction, User $by): void
    {
        $net = Money::toPaisa($slip->earned) - Money::toPaisa($slip->advance_deduction) - Money::toPaisa($otherDeduction) + Money::toPaisa($bonus);
        if ($net < 0) {
            throw ValidationException::withMessages(['other_deduction' => __('Net salary cannot be below zero.')]);
        }
        $slip->forceFill(['bonus' => Money::fromPaisa(Money::toPaisa($bonus)), 'other_deduction' => Money::fromPaisa(Money::toPaisa($otherDeduction)), 'net' => Money::fromPaisa($net)])->save();
        Audit::record('payroll.adjusted', $slip, ['bonus' => $slip->bonus, 'other_deduction' => $slip->other_deduction, 'net' => $slip->net], $by);
    }

    public function pay(Payslip $slip, Account $account, User $by): void
    {
        if ($slip->status === 'paid') {
            throw ValidationException::withMessages(['payslip' => __('Already paid.')]);
        }

        DB::transaction(function () use ($slip, $account, $by): void {
            $slip->loadMissing('staff');
            $expense = null;
            if (Money::toPaisa($slip->net) > 0) {
                $expense = $this->expenses->handle(
                    ExpenseCategory::query()->where('name', 'Salary')->firstOrFail(),
                    'Salary '.$slip->month.' · '.$slip->staff->name,
                    $slip->net,
                    $account,
                    now()->toDateString(),
                    $by,
                    null,
                    'payslip',
                    $slip->id,
                );
            }

            // Mark advances recovered, oldest first, up to the amount deducted.
            $left = Money::toPaisa($slip->advance_deduction);
            foreach (StaffAdvance::query()->where('staff_id', $slip->staff_id)->whereNull('payslip_id')->orderBy('id')->get() as $advance) {
                if ($left < Money::toPaisa($advance->amount)) {
                    break;
                }
                $left -= Money::toPaisa($advance->amount);
                $advance->forceFill(['payslip_id' => $slip->id])->save();
            }

            $slip->forceFill(['status' => 'paid', 'paid_at' => now(), 'expense_id' => $expense?->id])->save();
            Audit::record('payroll.paid', $slip, ['staff' => $slip->staff->name, 'month' => $slip->month, 'net' => $slip->net], $by);
        });
    }

    public function giveAdvance(Staff $staff, string $amount, Account $account, ?string $note, User $by): StaffAdvance
    {
        $paisa = Money::toPaisa($amount);
        if ($paisa <= 0) {
            throw ValidationException::withMessages(['amount' => __('Amount must be more than zero.')]);
        }

        return DB::transaction(function () use ($staff, $paisa, $account, $note, $by): StaffAdvance {
            $advance = new StaffAdvance;
            $advance->forceFill([
                'staff_id' => $staff->id, 'amount' => Money::fromPaisa($paisa), 'account_id' => $account->id,
                'given_on' => now()->toDateString(), 'note' => $note, 'created_by' => $by->id,
            ])->save();
            CashBook::post($account, -$paisa, 'advance', $advance->id, $by, 'Advance to '.$staff->name);
            Audit::record('staff.advance', $staff, ['amount' => $advance->amount], $by);

            return $advance;
        });
    }
}
