<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Enums\Permission;
use App\Http\Requests\Money\PermissionRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Advances, payroll, leave and attendance corrections (managers only). Rules depend on the route.
 */
class StaffActionRequest extends PermissionRequest
{
    protected function permission(): Permission
    {
        return Permission::ManageStaff;
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'staff.advances.store' => ['amount' => ['required', 'regex:'.self::MONEY], 'account_id' => ['required', 'integer', 'exists:accounts,id'], 'note' => ['nullable', 'string', 'max:200']],
            'staff.payroll.generate' => ['month' => ['required', 'date_format:Y-m']],
            'staff.payslips.update' => ['bonus' => ['required', 'regex:'.self::MONEY], 'other_deduction' => ['required', 'regex:'.self::MONEY]],
            'staff.payslips.pay' => ['account_id' => ['required', 'integer', 'exists:accounts,id']],
            'staff.leaves.store' => ['staff_id' => ['required', 'integer', 'exists:staff,id'], 'from_date' => ['required', 'date_format:Y-m-d'], 'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'], 'reason' => ['required', 'string', 'max:200']],
            'staff.leaves.update' => ['status' => ['required', Rule::in(['approved', 'rejected'])]],
            'staff.attendance.mark' => ['work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'status' => ['required', Rule::in(['present', 'late', 'absent', 'leave'])], 'note' => ['nullable', 'string', 'max:200']],
            default => [],
        };
    }
}
