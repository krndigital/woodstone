<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Final bill. Only print_count may change after creation; it is never deleted.
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property string $subtotal
 * @property string|null $discount_type
 * @property string $discount_value
 * @property string $discount_amount
 * @property string|null $discount_reason
 * @property int|null $discount_approved_by
 * @property string $service_percent
 * @property string $service_amount
 * @property string $vat_percent
 * @property string $vat_amount
 * @property string $total
 * @property string $paid_amount
 * @property string $due_amount
 * @property int $cashier_id
 * @property int $print_count
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Order $order
 * @property-read User $cashier
 */
class Bill extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Bill $bill): void {
            if (array_diff(array_keys($bill->getDirty()), ['print_count', 'updated_at']) !== []) {
                throw new LogicException('Bills cannot be changed after they are created.');
            }
        });
        static::deleting(fn () => throw new LogicException('Bills cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'service_percent' => 'decimal:2',
            'service_amount' => 'decimal:2',
            'vat_percent' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'print_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return HasMany<SalesReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    /**
     * @return HasMany<BillPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class)->orderBy('id');
    }
}
