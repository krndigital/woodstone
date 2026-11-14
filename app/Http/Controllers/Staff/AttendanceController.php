<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\Punch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PunchRequest;
use App\Models\Attendance;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Counter-tablet attendance screen (FR-12.2). Behind `can:staff.attendance`.
 */
class AttendanceController extends Controller
{
    public function index(): Response
    {
        $today = Attendance::query()->whereDate('work_date', now()->toDateString())->get()->keyBy('staff_id');

        return Inertia::render('staff/Attendance', [
            'staff' => Staff::query()->where('is_active', true)->whereNotNull('attendance_pin')->orderBy('name')->get()->map(fn (Staff $s): array => [
                'id' => $s->id, 'name' => $s->name, 'position' => $s->position,
                'in' => $today->get($s->id)?->check_in_at?->toIso8601String(),
                'out' => $today->get($s->id)?->check_out_at?->toIso8601String(),
                'status' => $today->get($s->id)?->status,
            ])->values()->all(),
        ]);
    }

    public function punch(PunchRequest $request, Punch $punch): RedirectResponse
    {
        $staff = Staff::query()->findOrFail($request->integer('staff_id'));
        $result = $punch->handle($staff, $request->string('pin')->toString(), $request->actor());
        Inertia::flash('toast', ['type' => $result['status'] === 'late' && $result['action'] === 'in' ? 'warning' : 'success', 'message' => $result['action'] === 'in'
            ? __(':name checked in:late.', ['name' => $staff->name, 'late' => $result['status'] === 'late' ? ' (late)' : ''])
            : __(':name checked out. See you!', ['name' => $staff->name])]);

        return back();
    }
}
