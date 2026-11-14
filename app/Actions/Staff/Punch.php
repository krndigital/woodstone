<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\Attendance;
use App\Models\Staff;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Check-in / check-out by PIN on the counter tablet (FR-12.2). Late = more than 10 minutes after
 * the shift starts. Wrong PINs are audited; the route is rate-limited.
 */
final class Punch
{
    public const GRACE_MINUTES = 10;

    /**
     * @return array{action: string, status: string}
     */
    public function handle(Staff $staff, string $pin, User $device): array
    {
        if (! $staff->is_active || $staff->attendance_pin === null || ! Hash::check($pin, $staff->attendance_pin)) {
            Audit::record('attendance.pin_failed', $staff, ['staff' => $staff->name], $device);

            throw ValidationException::withMessages(['pin' => __('Wrong PIN.')]);
        }

        $today = now()->toDateString();
        $row = Attendance::query()->where('staff_id', $staff->id)->whereDate('work_date', $today)->first();

        if ($row === null || $row->check_in_at === null) {
            $late = $staff->shift_start !== null && now()->greaterThan(now()->setTimeFromTimeString($staff->shift_start)->addMinutes(self::GRACE_MINUTES));
            $row ??= new Attendance;
            $row->forceFill([
                'staff_id' => $staff->id, 'work_date' => $today, 'check_in_at' => now(),
                'status' => $late ? 'late' : 'present', 'recorded_by' => $device->id,
            ])->save();

            return ['action' => 'in', 'status' => $row->status];
        }

        if ($row->check_out_at !== null) {
            throw ValidationException::withMessages(['pin' => __(':name already checked out today.', ['name' => $staff->name])]);
        }

        $row->forceFill(['check_out_at' => now()])->save();

        return ['action' => 'out', 'status' => $row->status];
    }
}
