<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an order that has nothing live in the kitchen (void sent items first, with approval).
 */
final class CancelOrder
{
    public function handle(Order $order, string $reason, User $user): void
    {
        EnsureOrderOpen::check($order);

        $live = $order->items()->whereNotIn('status', [OrderItemStatus::Draft->value, OrderItemStatus::Void->value])->exists();
        if ($live) {
            throw ValidationException::withMessages(['reason' => __('Void the items already sent to the kitchen first.')]);
        }

        DB::transaction(function () use ($order, $reason, $user): void {
            OrderItem::query()->where('order_id', $order->id)->where('status', OrderItemStatus::Draft->value)->delete();
            $order->forceFill(['status' => OrderStatus::Cancelled, 'cancel_reason' => $reason, 'closed_at' => now()])->save();
            $order->recalculate();
            $order->table?->setStatus(TableStatus::Free);

            Audit::record('order.cancelled', $order, ['number' => $order->number, 'reason' => $reason], $user);
        });
    }
}
