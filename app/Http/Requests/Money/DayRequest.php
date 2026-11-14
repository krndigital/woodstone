<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

class DayRequest extends PermissionRequest
{
    protected function permission(): Permission
    {
        return Permission::CloseDay;
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'opening_cash' => ['nullable', 'required_without:counted_cash', 'regex:'.self::MONEY],
            'counted_cash' => ['nullable', 'required_without:opening_cash', 'regex:'.self::MONEY],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
