<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\OrderItemStatus;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voids a line already sent to the kitchen. The row is kept (status void + reason + who approved).
 */
final class VoidOrderItem
{
    public function handle(OrderItem $line, string $reason, User $actor, User $approver): void
    {
        $order = $line->order;
        EnsureOrderOpen::check($order);

        if (! $line->status->isSent()) {
            throw ValidationException::withMessages(['reason' => __('Only items sent to the kitchen can be voided. Remove unsent items instead.')]);
        }

        DB::transaction(function () use ($line, $reason, $actor, $approver, $order): void {
            $line->forceFill([
                'status' => OrderItemStatus::Void,
                'void_reason' => $reason,
                'voided_by' => $actor->id,
                'void_approved_by' => $approver->id,
                'voided_at' => now(),
            ])->save();

            $order->recalculate();

            Audit::record('order.item_voided', $line, [
                'order' => $order->number, 'item' => $line->name, 'quantity' => $line->quantity,
                'amount' => $line->line_total, 'reason' => $reason, 'approved_by' => $approver->username,
            ], $actor);
        });
    }
}
