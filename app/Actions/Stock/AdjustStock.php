<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\StockItem;
use App\Models\User;
use App\Support\Audit;
use App\Support\Qty;
use Illuminate\Validation\ValidationException;

/**
 * Hand entries (FR-7.5, FR-7.7, FR-7.9): opening stock, wastage, staff meal, adjustment, and
 * physical count (the counted amount becomes the stock; the difference is recorded).
 */
final class AdjustStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(StockItem $item, StockMovementType $type, string $quantity, ?string $unitCost, string $reason, User $by): void
    {
        if (! in_array($type, StockMovementType::manual(), true)) {
            throw ValidationException::withMessages(['type' => __('Choose a valid entry type.')]);
        }

        $qty = Qty::toMilli($quantity);
        $milli = match ($type) {
            StockMovementType::Wastage, StockMovementType::StaffMeal => -abs($qty),
            StockMovementType::Opening => abs($qty),
            StockMovementType::Count => $qty - Qty::toMilli($item->refresh()->on_hand),
            default => $qty, // adjustment: + or −
        };

        if ($milli === 0 && $type !== StockMovementType::Count) {
            throw ValidationException::withMessages(['quantity' => __('Quantity must not be zero.')]);
        }
        if ($type === StockMovementType::Count && $qty < 0) {
            throw ValidationException::withMessages(['quantity' => __('Counted quantity cannot be negative.')]);
        }

        $cost = $type === StockMovementType::Opening && $unitCost !== null && $unitCost !== '' ? Qty::toCost($unitCost) : null;
        $this->ledger->move($item, $type, $milli, $cost, $by, $reason);

        Audit::record('stock.'.$type->value, $item, ['item' => $item->name, 'quantity' => Qty::fromMilli($milli), 'reason' => $reason, 'on_hand' => $item->on_hand], $by);
    }
}
