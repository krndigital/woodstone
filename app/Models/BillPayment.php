<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only.
 *
 * @property int $id
 * @property int $bill_id
 * @property string|null $payer
 * @property PaymentMethod $method
 * @property string $amount
 * @property int|null $customer_id
 * @property string|null $reference
 * @property bool $qr_checked
 * @property int $received_by
 * @property CarbonInterface $created_at
 * @property-read Customer|null $customer
 */
class BillPayment extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payments are append-only.'));
        static::deleting(fn () => throw new LogicException('Payments are append-only.'));
    }

    protected function casts(): array
    {
        return ['method' => PaymentMethod::class, 'amount' => 'decimal:2', 'qr_checked' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
