<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Kot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Joins two open orders (guests pushed tables together). Items move to $target; $source is
 * closed as cancelled with "merged into #n" and its table is freed for cleaning.
 */
final class MergeOrders
{
    public function handle(Order $target, Order $source, User $user): void
    {
        EnsureOrderOpen::check($target);
        EnsureOrderOpen::check($source);

        if ($target->id === $source->id) {
            throw ValidationException::withMessages(['source_order_id' => __('Choose a different order to merge.')]);
        }

        DB::transaction(function () use ($target, $source, $user): void {
            OrderItem::query()->where('order_id', $source->id)->update(['order_id' => $target->id, 'updated_at' => now()]);
            Kot::query()->where('order_id', $source->id)->update(['order_id' => $target->id, 'updated_at' => now()]);

            $source->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => 'Merged into #'.$target->number,
                'subtotal' => '0.00',
                'closed_at' => now(),
            ])->save();

            $target->forceFill(['guests' => $target->guests + $source->guests])->save();
            $target->recalculate();

            if ($source->dining_table_id !== null) {
                DiningTable::query()->whereKey($source->dining_table_id)->first()?->setStatus(TableStatus::Cleaning);
            }

            Audit::record('order.merged', $target, ['into' => $target->number, 'from' => $source->number], $user);
        });
    }
}
