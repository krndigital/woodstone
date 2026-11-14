<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Enums\Permission;
use App\Http\Requests\Money\PermissionRequest;
use App\Models\Staff;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StaffRequest extends PermissionRequest
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
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\- ]{7,20}$/'],
            'address' => ['nullable', 'string', 'max:150'],
            'citizenship_no' => ['nullable', 'string', 'max:40'],
            'position' => ['required', 'string', 'max:40'],
            'joined_on' => ['required', 'date_format:Y-m-d'],
            'salary_type' => ['required', Rule::in(['monthly', 'daily'])],
            'salary_amount' => ['required', 'regex:'.self::MONEY],
            'weekly_off' => ['nullable', 'integer', 'min:0', 'max:6'],
            'shift_start' => ['nullable', 'date_format:H:i'],
            'shift_end' => ['nullable', 'date_format:H:i'],
            'attendance_pin' => ['nullable', 'digits:4'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('staff', 'user_id')->ignore($staff instanceof Staff ? $staff->id : null)],
            'is_active' => ['boolean'],
        ];
    }
}
