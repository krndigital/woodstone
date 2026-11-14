<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only.
 *
 * @property int $id
 * @property int $stock_item_id
 * @property StockMovementType $type
 * @property string $quantity
 * @property string|null $unit_cost
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $reason
 * @property int $created_by
 * @property CarbonInterface $created_at
 * @property-read StockItem $item
 * @property-read User $creator
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Stock movements are append-only.'));
    }

    protected function casts(): array
    {
        return ['type' => StockMovementType::class, 'quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'reference_id' => 'integer', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
