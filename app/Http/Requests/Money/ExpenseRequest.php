<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

class ExpenseRequest extends PermissionRequest
{
    protected function permission(): Permission
    {
        return Permission::CreateExpense;
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
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'recurring_id' => ['nullable', 'integer', 'exists:recurring_expenses,id'],
        ];
    }
}
