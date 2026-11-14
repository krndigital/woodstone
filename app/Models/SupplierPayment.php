<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only.
 *
 * @property int $id
 * @property int $supplier_id
 * @property string $amount
 * @property int $account_id
 * @property CarbonInterface $paid_on
 * @property string|null $reference
 * @property string|null $note
 * @property int $created_by
 * @property CarbonInterface $created_at
 * @property-read Supplier $supplier
 * @property-read Account $account
 */
class SupplierPayment extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Supplier payments are append-only.'));
        static::deleting(fn () => throw new LogicException('Supplier payments are append-only.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
