<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only cash book row.
 *
 * @property int $id
 * @property int $account_id
 * @property string $amount
 * @property string $source_type
 * @property int|null $source_id
 * @property string|null $category
 * @property string|null $note
 * @property CarbonInterface $business_date
 * @property int $created_by
 * @property CarbonInterface $created_at
 * @property-read Account $account
 * @property-read User $creator
 */
class AccountTransaction extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cash book rows are append-only.'));
        static::deleting(fn () => throw new LogicException('Cash book rows are append-only.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'source_id' => 'integer', 'business_date' => 'date', 'created_at' => 'datetime'];
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
