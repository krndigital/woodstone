<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Enums\Permission;
use App\Http\Requests\Money\PermissionRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class PunchRequest extends PermissionRequest
{
    protected function permission(): Permission
    {
        return Permission::RecordAttendance;
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return ['staff_id' => ['required', 'integer', 'exists:staff,id'], 'pin' => ['required', 'digits:4']];
    }
}
