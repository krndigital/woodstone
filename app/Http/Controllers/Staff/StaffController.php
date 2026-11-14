<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\Payroll;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffActionRequest;
use App\Http\Requests\Staff\StaffRequest;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\StaffAdvance;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff profiles, advances, leave and attendance corrections (FR-12). Behind `can:staff.manage`.
 */
class StaffController extends Controller
{
    public function index(): Response
    {
        $month = now()->format('Y-m');
        $outstanding = [];
        foreach (StaffAdvance::query()->whereNull('payslip_id')->get(['staff_id', 'amount']) as $a) {
            $outstanding[$a->staff_id] = ($outstanding[$a->staff_id] ?? 0) + Money::toPaisa($a->amount);
        }

        $staff = Staff::query()->with('user:id,username')->orderByDesc('is_active')->orderBy('name')->get()->map(fn (Staff $s): array => [
            'id' => $s->id, 'name' => $s->name, 'phone' => $s->phone, 'address' => $s->address, 'position' => $s->position,
            'joined_on' => $s->joined_on->toDateString(), 'salary_type' => $s->salary_type, 'salary_amount' => $s->salary_amount,
            'weekly_off' => $s->weekly_off, 'shift_start' => $s->shift_start === null ? null : substr($s->shift_start, 0, 5),
            'shift_end' => $s->shift_end === null ? null : substr($s->shift_end, 0, 5),
            'has_pin' => $s->attendance_pin !== null, 'has_citizenship' => $s->citizenship_no !== null,
            'user_id' => $s->user_id, 'username' => $s->user?->username, 'is_active' => $s->is_active,
            'advance_due' => Money::fromPaisa($outstanding[$s->id] ?? 0),
            'this_month' => $s->is_active ? Payroll::summary($s, $month) : null,
        ])->values()->all();

        return Inertia::render('staff/Index', [
            'staff' => $staff,
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username'])->toArray(),
            'accounts' => Account::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])->toArray(),
            'leaves' => LeaveRequest::query()->with('staff:id,name')->latest('id')->limit(30)->get()->map(fn (LeaveRequest $l): array => [
                'id' => $l->id, 'staff' => $l->staff->name, 'from' => $l->from_date->toDateString(), 'to' => $l->to_date->toDateString(),
                'reason' => $l->reason, 'status' => $l->status,
            ])->values()->all(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $staff = new Staff($this->details($request));
        $this->applyProtected($staff, $request);
        $staff->forceFill(['is_active' => true])->save();
        Audit::record('staff.created', $staff, ['name' => $staff->name, 'salary' => $staff->salary_amount], $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $staff->name])]);

        return back();
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        $before = ['salary_type' => $staff->salary_type, 'salary_amount' => $staff->salary_amount, 'is_active' => $staff->is_active];
        $staff->fill($this->details($request));
        $this->applyProtected($staff, $request);
        $staff->forceFill(['is_active' => $request->boolean('is_active', true)])->save();
        Audit::record('staff.updated', $staff, ['name' => $staff->name, 'before' => $before, 'after' => ['salary_type' => $staff->salary_type, 'salary_amount' => $staff->salary_amount, 'is_active' => $staff->is_active]], $request->actor());

        return back();
    }

    public function advance(StaffActionRequest $request, Staff $staff, Payroll $payroll): RedirectResponse
    {
        $payroll->giveAdvance($staff, $request->string('amount')->toString(), Account::query()->findOrFail($request->integer('account_id')), $request->optionalString('note'), $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Advance given to :name.', ['name' => $staff->name])]);

        return back();
    }

    public function storeLeave(StaffActionRequest $request): RedirectResponse
    {
        $leave = new LeaveRequest;
        $leave->forceFill([
            'staff_id' => $request->integer('staff_id'), 'from_date' => $request->string('from_date')->toString(),
            'to_date' => $request->string('to_date')->toString(), 'reason' => $request->string('reason')->trim()->toString(), 'status' => 'pending',
        ])->save();
        Audit::record('staff.leave_requested', $leave, ['from' => $leave->from_date->toDateString(), 'to' => $leave->to_date->toDateString()], $request->actor());

        return back();
    }

    public function decideLeave(StaffActionRequest $request, LeaveRequest $leave): RedirectResponse
    {
        $leave->forceFill(['status' => $request->string('status')->toString(), 'decided_by' => $request->actor()->id, 'decided_at' => now()])->save();
        Audit::record('staff.leave_'.$leave->status, $leave, ['staff_id' => $leave->staff_id], $request->actor());

        return back();
    }

    public function markAttendance(StaffActionRequest $request, Staff $staff): RedirectResponse
    {
        $row = Attendance::query()->where('staff_id', $staff->id)->whereDate('work_date', $request->string('work_date')->toString())->first() ?? new Attendance;
        $before = $row->exists ? $row->status : null;
        $row->forceFill([
            'staff_id' => $staff->id, 'work_date' => $request->string('work_date')->toString(),
            'status' => $request->string('status')->toString(), 'note' => $request->optionalString('note'), 'recorded_by' => $request->actor()->id,
        ])->save();
        Audit::record('attendance.corrected', $row, ['staff' => $staff->name, 'date' => $row->work_date->toDateString(), 'from' => $before, 'to' => $row->status], $request->actor());

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function details(StaffRequest $request): array
    {
        return [
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->optionalString('phone'),
            'address' => $request->optionalString('address'),
            'position' => $request->string('position')->trim()->toString(),
            'joined_on' => $request->string('joined_on')->toString(),
            'weekly_off' => $request->filled('weekly_off') ? $request->integer('weekly_off') : null,
            'shift_start' => $request->optionalString('shift_start'),
            'shift_end' => $request->optionalString('shift_end'),
        ];
    }

    /**
     * Salary, PIN, linked login and citizenship number are set only through this method.
     */
    private function applyProtected(Staff $staff, StaffRequest $request): void
    {
        $staff->forceFill([
            'salary_type' => $request->string('salary_type')->toString(),
            'salary_amount' => Money::fromPaisa(Money::toPaisa($request->string('salary_amount')->toString())),
            'user_id' => $request->filled('user_id') ? $request->integer('user_id') : null,
        ]);
        if ($request->filled('attendance_pin')) {
            $staff->forceFill(['attendance_pin' => $request->string('attendance_pin')->toString()]);
        }
        if ($request->filled('citizenship_no')) {
            $staff->forceFill(['citizenship_no' => $request->string('citizenship_no')->trim()->toString()]);
        }
    }
}
