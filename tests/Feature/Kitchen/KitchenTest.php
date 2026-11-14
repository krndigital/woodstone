<?php

declare(strict_types=1);

namespace Tests\Feature\Kitchen;

use App\Enums\OrderItemStatus;
use App\Enums\Role;
use App\Models\DiningTable;
use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KitchenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Order, 1: Station, 2: Station}
     */
    private function sentOrder(): array
    {
        $waiter = User::factory()->role(Role::Waiter)->create();
        $kitchen = Station::factory()->create(['name' => 'Kitchen']);
        $bar = Station::factory()->create(['name' => 'Bar']);
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => DiningTable::factory()->create()->id]);
        $order = Order::query()->firstOrFail();
        foreach ([$kitchen, $kitchen, $bar] as $station) {
            $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create(['station_id' => $station->id])->id, 'quantity' => 1]);
        }
        $this->actingAs($waiter)->post(route('pos.orders.kot', $order));

        return [$order, $kitchen, $bar];
    }

    public function test_access_by_role(): void
    {
        $this->get(route('kitchen.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->get(route('kitchen.index'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Kitchen)->create())->get(route('kitchen.index'))->assertOk();
    }

    public function test_tickets_are_filtered_by_station(): void
    {
        [, $kitchen] = $this->sentOrder();
        $cook = User::factory()->role(Role::Kitchen)->create();

        $this->actingAs($cook)->get(route('kitchen.index'))
            ->assertInertia(fn (Assert $page) => $page->component('kitchen/Index')->has('kots', 2));

        $this->actingAs($cook)->get(route('kitchen.index', ['station' => $kitchen->id]))
            ->assertInertia(fn (Assert $page) => $page->has('kots', 1)->has('kots.0.items', 2)->where('stationId', $kitchen->id));
    }

    public function test_bump_moves_items_to_ready_and_ready_tickets_leave_the_screen(): void
    {
        $this->sentOrder();
        $cook = User::factory()->role(Role::Kitchen)->create();
        $line = OrderItem::query()->firstOrFail();

        $this->actingAs($cook)->patch(route('kitchen.items.bump', $line))->assertSessionHasNoErrors();
        $this->assertSame(OrderItemStatus::Preparing, $line->refresh()->status);
        $this->actingAs($cook)->patch(route('kitchen.items.bump', $line))->assertSessionHasNoErrors();
        $this->assertSame(OrderItemStatus::Ready, $line->refresh()->status);
        $this->assertNotNull($line->ready_at);
        $this->actingAs($cook)->patch(route('kitchen.items.bump', $line))->assertSessionHasErrors('item');

        foreach (Kot::query()->get() as $kot) {
            $this->actingAs($cook)->patch(route('kitchen.kots.bump', $kot))->assertSessionHasNoErrors();
        }
        $this->assertSame(3, OrderItem::query()->where('status', 'ready')->count());

        // Ready items still show until served; once served the ticket leaves.
        $waiter = User::factory()->role(Role::Waiter)->create();
        foreach (OrderItem::query()->get() as $item) {
            $this->actingAs($waiter)->patch(route('pos.items.served', $item));
        }
        $this->actingAs($cook)->get(route('kitchen.index'))->assertInertia(fn (Assert $page) => $page->has('kots', 0));

        $this->actingAs($waiter)->patch(route('kitchen.items.bump', $line))->assertForbidden();
    }
}
