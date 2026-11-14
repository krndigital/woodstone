<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kitchen;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\BumpRequest;
use App\Models\Kot;
use App\Models\OrderItem;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kitchen & bar display (step 6, FR-5). Behind `can:kitchen.view`. The page polls every few
 * seconds; Reverb push can replace polling later without changing this controller.
 */
class KitchenController extends Controller
{
    public function index(Request $request): Response
    {
        $stationId = $request->integer('station') ?: null;
        $live = array_map(fn (OrderItemStatus $s): string => $s->value, OrderItemStatus::inKitchen());

        $kots = Kot::query()
            ->with(['order.diningTable', 'station:id,name', 'creator:id,name', 'items'])
            ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Open->value))
            ->whereHas('items', fn ($q) => $q->whereIn('status', $live))
            ->orderBy('id')
            ->limit(60)
            ->get()
            ->map(fn (Kot $k): array => [
                'id' => $k->id,
                'number' => $k->number,
                'station' => $k->station->name,
                'order_label' => $k->order->label(),
                'order_number' => $k->order->number,
                'order_type' => $k->order->type->value,
                'note' => $k->order->note,
                'waiter' => $k->creator->name,
                'created_at' => $k->created_at?->toIso8601String(),
                'items' => $k->items->map(fn (OrderItem $i): array => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                    'modifiers' => array_map(fn (array $m): string => $m['name'], $i->modifiers ?? []),
                    'note' => $i->note,
                    'status' => $i->status->value,
                ])->values()->all(),
            ])
            ->values()->all();

        return Inertia::render('kitchen/Index', [
            'kots' => $kots,
            'stations' => Station::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name'])->toArray(),
            'stationId' => $stationId,
        ]);
    }

    /**
     * Sent → Preparing → Ready.
     */
    public function bump(BumpRequest $request, OrderItem $orderItem): RedirectResponse
    {
        $this->advance($orderItem, throw: true);

        return back();
    }

    /**
     * Whole ticket ready.
     */
    public function bumpKot(BumpRequest $request, Kot $kot): RedirectResponse
    {
        foreach ($kot->items as $item) {
            while ($this->advance($item, throw: false)) {
                // advance until ready
            }
        }

        return back();
    }

    private function advance(OrderItem $item, bool $throw): bool
    {
        $next = $item->status->kitchenNext();

        if ($next === null || ! $item->order->isOpen()) {
            if ($throw) {
                throw ValidationException::withMessages(['item' => __(':item is already :status.', ['item' => $item->name, 'status' => mb_strtolower($item->status->label())])]);
            }

            return false;
        }

        $item->forceFill([
            'status' => $next,
            'ready_at' => $next === OrderItemStatus::Ready ? now() : $item->ready_at,
        ])->save();

        return true;
    }
}
