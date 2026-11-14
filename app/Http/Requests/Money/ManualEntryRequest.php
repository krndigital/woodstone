<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ManualEntryRequest extends PermissionRequest
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
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'regex:'.self::MONEY],
            'category' => ['required', Rule::in(['owner_capital', 'owner_withdrawal', 'loan', 'float', 'other'])],
            'note' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}
