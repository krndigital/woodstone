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
use App\Support\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OpenOrder
{
    /**
     * @param  array{guests?: int, customer_name?: string|null, customer_phone?: string|null, delivery_address?: string|null, note?: string|null}  $details
     */
    public function handle(User $waiter, OrderType $type, ?int $tableId, array $details = []): Order
    {
        return DB::transaction(function () use ($waiter, $type, $tableId, $details): Order {
            $table = null;

            if ($type === OrderType::DineIn) {
                $table = DiningTable::query()->whereKey($tableId)->where('is_active', true)->lockForUpdate()->first();

                if ($table === null) {
                    throw ValidationException::withMessages(['dining_table_id' => __('Choose a table.')]);
                }

                if (! $table->status->acceptsNewOrder() || Order::query()->where('dining_table_id', $table->id)->where('status', OrderStatus::Open->value)->exists()) {
                    throw ValidationException::withMessages(['dining_table_id' => __(':table is :status. Open its current order instead.', ['table' => $table->label(), 'status' => $table->status->label()])]);
                }
            }

            $order = new Order;
            $order->forceFill([
                'number' => (string) NumberSequence::next('order'),
                'type' => $type,
                'dining_table_id' => $table?->id,
                'guests' => max(1, $details['guests'] ?? 1),
                'status' => OrderStatus::Open,
                'waiter_id' => $waiter->id,
                'customer_name' => $details['customer_name'] ?? null,
                'customer_phone' => $details['customer_phone'] ?? null,
                'delivery_address' => $type === OrderType::Delivery ? ($details['delivery_address'] ?? null) : null,
                'note' => $details['note'] ?? null,
                'subtotal' => '0.00',
                'opened_at' => now(),
            ])->save();

            $table?->setStatus(TableStatus::Occupied);

            Audit::record('order.opened', $order, ['number' => $order->number, 'type' => $type->value, 'table' => $table?->code], $waiter);

            return $order;
        });
    }
}
