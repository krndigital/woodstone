<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

class SupplierPaymentRequest extends PermissionRequest
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
            'amount' => ['required', 'regex:'.self::MONEY],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
