<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only: rows are never updated or deleted. Corrections are new rows.
 *
 * @property int $id
 * @property int $customer_id
 * @property LedgerType $type
 * @property string $amount
 * @property int|null $bill_id
 * @property int|null $related_customer_id
 * @property PaymentMethod|null $method
 * @property string|null $reference
 * @property string|null $note
 * @property int $created_by
 * @property CarbonInterface $created_at
 * @property-read Bill|null $bill
 * @property-read Customer|null $relatedCustomer
 * @property-read User $creator
 */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }

    protected function casts(): array
    {
        return ['type' => LedgerType::class, 'method' => PaymentMethod::class, 'amount' => 'decimal:2', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function relatedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'related_customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
