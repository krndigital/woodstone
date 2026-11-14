<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $stock_item_id
 * @property string $quantity
 * @property string $expected_rate
 * @property string $received_quantity
 * @property-read StockItem $stockItem
 */
class PurchaseOrderLine extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'expected_rate' => 'decimal:2', 'received_quantity' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
