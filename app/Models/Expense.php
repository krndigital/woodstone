<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Only the void fields may change after an expense is saved.
 *
 * @property int $id
 * @property string $number
 * @property int $expense_category_id
 * @property string $description
 * @property string $amount
 * @property int $account_id
 * @property CarbonInterface $spent_on
 * @property string $kind
 * @property string|null $receipt_path
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int $created_by
 * @property CarbonInterface|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read ExpenseCategory $category
 * @property-read Account $account
 * @property-read User $creator
 */
class Expense extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Expense $expense): void {
            if (array_diff(array_keys($expense->getDirty()), ['voided_at', 'voided_by', 'void_reason', 'updated_at']) !== []) {
                throw new LogicException('Expenses cannot be edited. Void and enter again.');
            }
        });
        static::deleting(fn () => throw new LogicException('Expenses cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'spent_on' => 'date', 'voided_at' => 'datetime', 'source_id' => 'integer'];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
