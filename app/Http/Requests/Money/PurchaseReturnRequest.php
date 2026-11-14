<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

class PurchaseReturnRequest extends PermissionRequest
{
    protected function permission(): Permission
    {
        return Permission::ManageStock;
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'quantity' => ['required', 'regex:/^\d{1,10}(\.\d{1,3})?$/'],
            'amount' => ['required', 'regex:'.self::MONEY],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}
