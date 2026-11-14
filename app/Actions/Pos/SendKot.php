<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\OrderItemStatus;
use App\Models\Kot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Audit;
use App\Support\ItemEvents;
use App\Support\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sends all draft lines to the kitchen: one KOT per station (kitchen, bar, ...).
 */
final class SendKot
{
    /**
     * @return list<Kot>
     */
    public function handle(Order $order, User $user): array
    {
        EnsureOrderOpen::check($order);

        return DB::transaction(function () use ($order, $user): array {
            $drafts = OrderItem::query()->where('order_id', $order->id)->where('status', OrderItemStatus::Draft->value)->lockForUpdate()->get();

            if ($drafts->isEmpty()) {
                throw ValidationException::withMessages(['order' => __('Nothing new to send.')]);
            }

            $kots = [];
            foreach ($drafts->groupBy('station_id') as $stationId => $lines) {
                $kot = new Kot;
                $kot->forceFill([
                    'number' => NumberSequence::next('kot'),
                    'order_id' => $order->id,
                    'station_id' => (int) $stationId,
                    'created_by' => $user->id,
                ])->save();

                OrderItem::query()->whereIn('id', $lines->pluck('id'))->update([
                    'kot_id' => $kot->id,
                    'status' => OrderItemStatus::Sent->value,
                    'sent_at' => now(),
                    'updated_at' => now(),
                ]);

                ItemEvents::log($lines->pluck('id')->map(fn ($id): int => (int) $id), 'sent', $user->id);
                $kots[] = $kot;
            }

            Audit::record('order.kot_sent', $order, ['kots' => array_map(fn (Kot $k): int => $k->number, $kots), 'lines' => $drafts->count()], $user);

            return $kots;
        });
    }
}
