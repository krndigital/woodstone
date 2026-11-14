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
 * @property string $amount
 * @property PaymentMethod $refund_method
 * @property bool $restock
 * @property bool $full_cancel
 * @property string $reason
 * @property int $created_by
 * @property int $approved_by
 * @property CarbonInterface $created_at
 * @property-read Bill $bill
 * @property-read User $creator
 * @property-read User $approver
 */
class SalesReturn extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Sales returns are append-only.'));
        static::deleting(fn () => throw new LogicException('Sales returns are append-only.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refund_method' => PaymentMethod::class, 'restock' => 'boolean', 'full_cancel' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
