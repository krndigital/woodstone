<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MoveOrder
{
    public function handle(Order $order, int $targetTableId, User $user): void
    {
        EnsureOrderOpen::check($order);

        if ($order->type !== OrderType::DineIn || $order->dining_table_id === null) {
            throw ValidationException::withMessages(['dining_table_id' => __('Only dine-in orders can move tables.')]);
        }

        DB::transaction(function () use ($order, $targetTableId, $user): void {
            $target = DiningTable::query()->whereKey($targetTableId)->where('is_active', true)->lockForUpdate()->first();
            $from = DiningTable::query()->whereKey($order->dining_table_id)->lockForUpdate()->firstOrFail();

            if ($target === null || $target->id === $from->id || ! $target->status->acceptsNewOrder()
                || Order::query()->where('dining_table_id', $target->id)->where('status', OrderStatus::Open->value)->exists()) {
                throw ValidationException::withMessages(['dining_table_id' => __('Choose a free table.')]);
            }

            $order->forceFill(['dining_table_id' => $target->id])->save();
            $target->setStatus(TableStatus::Occupied);
            $from->setStatus(TableStatus::Cleaning);

            Audit::record('order.moved', $order, ['number' => $order->number, 'from' => $from->code, 'to' => $target->code], $user);
        });
    }
}
