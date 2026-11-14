<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderItemStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $menu_item_id
 * @property int $station_id
 * @property int|null $kot_id
 * @property string $name
 * @property string $unit_price
 * @property list<array{id: int, name: string, price: string}>|null $modifiers
 * @property int $quantity
 * @property string $line_total
 * @property string|null $note
 * @property OrderItemStatus $status
 * @property int $added_by
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $ready_at
 * @property CarbonInterface|null $served_at
 * @property string|null $void_reason
 * @property int|null $voided_by
 * @property int|null $void_approved_by
 * @property CarbonInterface|null $voided_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Order $order
 * @property-read Kot|null $kot
 * @property-read MenuItem|null $menuItem
 */
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'modifiers' => 'array',
            'quantity' => 'integer',
            'status' => OrderItemStatus::class,
            'sent_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<Kot, $this>
     */
    public function kot(): BelongsTo
    {
        return $this->belongsTo(Kot::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
