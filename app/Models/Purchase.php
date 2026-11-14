<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Final once saved (corrections come with purchase returns, step 10).
 *
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property int|null $purchase_order_id
 * @property string|null $supplier_bill_no
 * @property CarbonInterface $purchased_on
 * @property string $total
 * @property string $paid_amount
 * @property string|null $paid_method
 * @property string|null $note
 * @property int $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Supplier $supplier
 * @property-read User $creator
 */
class Purchase extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Purchases cannot be changed after saving.'));
        static::deleting(fn () => throw new LogicException('Purchases cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['purchased_on' => 'date', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class)->orderBy('id');
    }
}
