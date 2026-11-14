<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

class RecurringExpenseRequest extends PermissionRequest
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
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'regex:'.self::MONEY],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:28'],
            'is_active' => ['boolean'],
        ];
    }
}
