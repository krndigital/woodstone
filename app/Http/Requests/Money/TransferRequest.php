<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

class TransferRequest extends PermissionRequest
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
            'from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'to_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'regex:'.self::MONEY],
            'note' => ['nullable', 'string', 'max:150'],
        ];
    }
}
