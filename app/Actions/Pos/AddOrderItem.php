<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\OrderItemStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a draft line. The price comes from the menu (never from the browser) and is snapshotted.
 */
final class AddOrderItem
{
    /**
     * @param  list<int>  $modifierIds
     */
    public function handle(Order $order, MenuItem $menuItem, int $quantity, array $modifierIds, ?string $note, User $user): OrderItem
    {
        EnsureOrderOpen::check($order);

        if (! $menuItem->isOrderableAt(now())) {
            throw ValidationException::withMessages(['menu_item_id' => __(':item is not available right now.', ['item' => $menuItem->name])]);
        }

        $modifiers = $menuItem->modifiers()->whereIn('id', $modifierIds)->get();
        if ($modifiers->count() !== count(array_unique($modifierIds))) {
            throw ValidationException::withMessages(['modifier_ids' => __('One of the extras does not belong to this item.')]);
        }

        $unit = Money::toPaisa($menuItem->price);
        $snapshot = [];
        foreach ($modifiers as $modifier) {
            $unit += Money::toPaisa($modifier->price);
            $snapshot[] = ['id' => $modifier->id, 'name' => $modifier->name, 'price' => $modifier->price];
        }

        return DB::transaction(function () use ($order, $menuItem, $quantity, $note, $user, $unit, $snapshot): OrderItem {
            $line = new OrderItem;
            $line->forceFill([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
                'station_id' => $menuItem->station_id,
                'name' => $menuItem->name,
                'unit_price' => Money::fromPaisa($unit),
                'modifiers' => $snapshot === [] ? null : $snapshot,
                'quantity' => $quantity,
                'line_total' => Money::fromPaisa($unit * $quantity),
                'note' => $note,
                'status' => OrderItemStatus::Draft,
                'added_by' => $user->id,
            ])->save();

            $order->recalculate();

            return $line;
        });
    }
}
