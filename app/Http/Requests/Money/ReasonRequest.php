<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Void an expense (cash managers only).
 */
class ReasonRequest extends PermissionRequest
{
    protected function permission(): Permission
    {
        return Permission::ManageCash;
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:200']];
    }
}
