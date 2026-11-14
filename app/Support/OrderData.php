<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Kot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemModifier;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Arrays sent to the POS pages. Never includes food cost.
 */
final class OrderData
{
    /**
     * @return array<string, mixed>
     */
    public static function order(Order $order): array
    {
        $order->loadMissing(['diningTable', 'waiter:id,name', 'items', 'kots.station:id,name']);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'label' => $order->label(),
            'type' => $order->type->value,
            'type_label' => $order->type->label(),
            'status' => $order->status->value,
            'table_id' => $order->dining_table_id,
            'guests' => $order->guests,
            'waiter' => $order->waiter->name,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_address' => $order->delivery_address,
            'note' => $order->note,
            'subtotal' => $order->subtotal,
            'opened_at' => $order->opened_at->toIso8601String(),
            'items' => $order->items->map(fn (OrderItem $i): array => self::item($i))->values()->all(),
            'kots' => $order->kots->map(fn (Kot $k): array => [
                'id' => $k->id,
                'number' => $k->number,
                'station' => $k->station->name,
                'printed' => $k->printed_at !== null,
                'created_at' => $k->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(OrderItem $i): array
    {
        return [
            'id' => $i->id,
            'menu_item_id' => $i->menu_item_id,
            'name' => $i->name,
            'unit_price' => $i->unit_price,
            'modifiers' => $i->modifiers ?? [],
            'quantity' => $i->quantity,
            'line_total' => $i->line_total,
            'note' => $i->note,
            'status' => $i->status->value,
            'status_label' => $i->status->label(),
            'void_reason' => $i->void_reason,
            'sent_at' => $i->sent_at?->toIso8601String(),
        ];
    }

    /**
     * Open orders keyed for the floor view.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function openOrders(): array
    {
        return Order::query()
            ->with(['diningTable', 'waiter:id,name'])
            ->withCount(['items as live_items_count' => fn ($q) => $q->where('status', '!=', 'void')])
            ->where('status', OrderStatus::Open->value)
            ->orderBy('opened_at')
            ->get()
            ->map(fn (Order $o): array => [
                'id' => $o->id,
                'number' => $o->number,
                'label' => $o->label(),
                'type' => $o->type->value,
                'table_id' => $o->dining_table_id,
                'guests' => $o->guests,
                'waiter' => $o->waiter->name,
                'customer_name' => $o->customer_name,
                'subtotal' => $o->subtotal,
                'items_count' => (int) $o->getAttribute('live_items_count'),
                'opened_at' => $o->opened_at->toIso8601String(),
            ])
            ->values()->all();
    }

    /**
     * Active menu for taking orders.
     *
     * @return array{categories: array<int, array<string, mixed>>, items: array<int, array<string, mixed>>}
     */
    public static function menu(): array
    {
        $now = now();

        $categories = MenuCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (MenuCategory $c): array => ['id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id])
            ->values()->all();

        $items = MenuItem::query()->with('modifiers')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (MenuItem $m): array => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'category_id' => $m->category_id,
                'price' => $m->price,
                'is_veg' => $m->is_veg,
                'orderable' => $m->isOrderableAt($now),
                'image_url' => $m->imageUrl(),
                'modifiers' => $m->modifiers->map(fn (MenuItemModifier $x): array => ['id' => $x->id, 'name' => $x->name, 'price' => $x->price])->values()->all(),
            ])
            ->values()->all();

        return ['categories' => $categories, 'items' => $items];
    }
}
