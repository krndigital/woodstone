<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $expense_category_id
 * @property string $description
 * @property string $amount
 * @property int $day_of_month
 * @property string|null $last_month
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read ExpenseCategory $category
 */
#[Fillable(['expense_category_id', 'description', 'amount', 'day_of_month'])]
class RecurringExpense extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'day_of_month' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
