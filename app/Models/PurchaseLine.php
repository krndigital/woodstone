<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_id
 * @property int $stock_item_id
 * @property string $quantity
 * @property bool $in_purchase_unit
 * @property string $base_quantity
 * @property string $rate
 * @property string $line_total
 * @property-read StockItem $stockItem
 */
class PurchaseLine extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'in_purchase_unit' => 'boolean', 'base_quantity' => 'decimal:3', 'rate' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
