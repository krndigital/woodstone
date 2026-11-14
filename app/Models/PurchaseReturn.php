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
 * @property int|null $purchase_id
 * @property int $stock_item_id
 * @property string $quantity
 * @property string $amount
 * @property string $reason
 * @property int $created_by
 * @property CarbonInterface $created_at
 * @property-read StockItem $stockItem
 * @property-read Supplier $supplier
 */
class PurchaseReturn extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Purchase returns are append-only.'));
        static::deleting(fn () => throw new LogicException('Purchase returns are append-only.'));
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'amount' => 'decimal:2', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
