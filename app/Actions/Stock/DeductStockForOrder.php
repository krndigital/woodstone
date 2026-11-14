<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\OrderItemStatus;
use App\Enums\StockMovementType;
use App\Models\Bill;
use App\Models\Order;
use App\Models\RecipeLine;
use App\Models\User;
use App\Support\Qty;

/**
 * When a bill is paid, each sold plate reduces its recipe ingredients (FR-7.3).
 * Stock may go below zero; the stock page shows it so the store keeper can count.
 */
final class DeductStockForOrder
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(Order $order, Bill $bill, User $by): void
    {
        $sold = [];
        foreach ($order->items()->where('status', '!=', OrderItemStatus::Void->value)->get(['menu_item_id', 'quantity']) as $line) {
            $sold[$line->menu_item_id] = ($sold[$line->menu_item_id] ?? 0) + $line->quantity;
        }
        if ($sold === []) {
            return;
        }

        $use = [];
        foreach (RecipeLine::query()->with('stockItem')->whereIn('menu_item_id', array_keys($sold))->get() as $recipe) {
            $id = $recipe->stock_item_id;
            $use[$id] ??= ['item' => $recipe->stockItem, 'milli' => 0];
            $use[$id]['milli'] += Qty::toMilli($recipe->quantity) * $sold[$recipe->menu_item_id];
        }

        ksort($use); // fixed lock order
        foreach ($use as $u) {
            $this->ledger->move($u['item'], StockMovementType::Sale, -$u['milli'], null, $by, 'Bill '.$bill->number, 'bill', $bill->id);
        }
    }
}
