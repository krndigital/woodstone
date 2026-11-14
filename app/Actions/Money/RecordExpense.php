<?php

declare(strict_types=1);

namespace App\Actions\Money;

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Audit;
use App\Support\CashBook;
use App\Support\Money;
use App\Support\NumberSequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Expense entry (FR-8.2). Posts the money out of the chosen account. Receipts are stored on the
 * private disk and only served to signed-in users with the expense permission.
 */
final class RecordExpense
{
    public function handle(ExpenseCategory $category, string $description, string $amount, Account $account, string $spentOn, User $by, ?UploadedFile $receipt = null, ?string $sourceType = null, ?int $sourceId = null): Expense
    {
        $paisa = Money::toPaisa($amount);
        if ($paisa <= 0) {
            throw ValidationException::withMessages(['amount' => __('Amount must be more than zero.')]);
        }
        if (! $category->is_active || ! $account->is_active) {
            throw ValidationException::withMessages(['expense_category_id' => __('Choose an active category and account.')]);
        }

        return DB::transaction(function () use ($category, $description, $paisa, $account, $spentOn, $by, $receipt, $sourceType, $sourceId): Expense {
            $expense = new Expense;
            $expense->forceFill([
                'number' => 'E-'.str_pad((string) NumberSequence::next('expense'), 5, '0', STR_PAD_LEFT),
                'expense_category_id' => $category->id,
                'description' => $description,
                'amount' => Money::fromPaisa($paisa),
                'account_id' => $account->id,
                'spent_on' => $spentOn,
                'kind' => $category->kind,
                'receipt_path' => $receipt?->store('receipts', 'local'),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'created_by' => $by->id,
            ])->save();

            CashBook::post($account, -$paisa, 'expense', $expense->id, $by, $category->name.' · '.$description, null, $spentOn);
            Audit::record('expense.created', $expense, ['number' => $expense->number, 'category' => $category->name, 'amount' => $expense->amount], $by);

            return $expense;
        });
    }

    public function void(Expense $expense, string $reason, User $by): void
    {
        if ($expense->voided_at !== null) {
            throw ValidationException::withMessages(['reason' => __('This expense is already void.')]);
        }
        if ($expense->source_type === 'payslip') {
            throw ValidationException::withMessages(['reason' => __('Salary payments are corrected from payroll.')]);
        }

        DB::transaction(function () use ($expense, $reason, $by): void {
            $expense->forceFill(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason])->save();
            CashBook::post($expense->account, Money::toPaisa($expense->amount), 'expense_void', $expense->id, $by, 'Void '.$expense->number.': '.$reason);
            Audit::record('expense.voided', $expense, ['number' => $expense->number, 'amount' => $expense->amount, 'reason' => $reason], $by);
        });
    }
}
