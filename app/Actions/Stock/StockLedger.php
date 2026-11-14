<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of stock. Adds one movement row, updates the on-hand cache and, for stock
 * coming in with a cost, the weighted average cost (FR-6.4). Locks the item row.
 */
final class StockLedger
{
    /**
     * @param  int  $milli  quantity in thousandths of the base unit, + in / − out
     * @param  int|null  $unitCost  cost per base unit in ten-thousandths of a rupee
     */
    public function move(StockItem $item, StockMovementType $type, int $milli, ?int $unitCost, User $by, ?string $reason = null, ?string $refType = null, ?int $refId = null): StockMovement
    {
        return DB::transaction(function () use ($item, $type, $milli, $unitCost, $by, $reason, $refType, $refId): StockMovement {
            $locked = StockItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $onHand = Qty::toMilli($locked->on_hand);
            $avg = Qty::toCost($locked->avg_cost);

            if ($milli > 0 && $unitCost !== null) {
                $avg = $onHand > 0 ? Qty::div($onHand * $avg + $milli * $unitCost, $onHand + $milli) : $unitCost;
            }

            $movement = new StockMovement;
            $movement->forceFill([
                'stock_item_id' => $locked->id,
                'type' => $type,
                'quantity' => Qty::fromMilli($milli),
                'unit_cost' => Qty::fromCost($unitCost ?? $avg),
                'reference_type' => $refType,
                'reference_id' => $refId,
                'reason' => $reason,
                'created_by' => $by->id,
                'created_at' => now(),
            ])->save();

            $locked->forceFill(['on_hand' => Qty::fromMilli($onHand + $milli), 'avg_cost' => Qty::fromCost($avg)])->save();
            $item->setRawAttributes($locked->getAttributes(), true);

            return $movement;
        });
    }
}
