<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\MenuItemModifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private function waiter(): User
    {
        return User::factory()->role(Role::Waiter)->create();
    }

    private function openTableOrder(User $user, ?DiningTable $table = null): Order
    {
        $table ??= DiningTable::factory()->create();
        $this->actingAs($user)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => $table->id, 'guests' => 2])
            ->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function addSent(Order $order, User $user, ?MenuItem $item = null): OrderItem
    {
        $item ??= MenuItem::factory()->create(['price' => '300.00']);
        $this->actingAs($user)->post(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id, 'quantity' => 1]);
        $this->actingAs($user)->post(route('pos.orders.kot', $order))->assertSessionHasNoErrors();

        return OrderItem::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    public function test_access_by_role(): void
    {
        $this->get(route('pos.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Kitchen)->create())->get(route('pos.index'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::StoreKeeper)->create())->post(route('pos.orders.store'), ['type' => 'takeaway'])->assertForbidden();

        DiningTable::factory()->create();
        $this->actingAs($this->waiter())->get(route('pos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('pos/Index')->has('tables', 1)->has('orders', 0));
    }

    public function test_open_a_dine_in_order_marks_the_table_occupied(): void
    {
        $waiter = $this->waiter();
        $table = DiningTable::factory()->create();

        $order = $this->openTableOrder($waiter, $table);

        $this->assertSame(TableStatus::Occupied, $table->refresh()->status);
        $this->assertSame($waiter->id, $order->waiter_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.opened', 'subject_id' => $order->id]);

        // A second order on the same table is refused.
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => $table->id])
            ->assertSessionHasErrors('dining_table_id');
        $this->assertSame(1, Order::query()->count());

        $this->actingAs($waiter)->get(route('pos.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('pos/Order')->where('order.id', $order->id));
    }

    public function test_open_order_validation(): void
    {
        $waiter = $this->waiter();

        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in'])->assertSessionHasErrors('dining_table_id');
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'delivery'])->assertSessionHasErrors(['customer_phone', 'delivery_address']);
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'picnic'])->assertSessionHasErrors('type');

        $dirty = DiningTable::factory()->status(TableStatus::Cleaning)->create();
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => $dirty->id])->assertSessionHasErrors('dining_table_id');

        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'takeaway', 'customer_name' => 'Hari'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['type' => 'takeaway', 'dining_table_id' => null, 'customer_name' => 'Hari']);
    }

    public function test_items_use_the_menu_price_with_extras_never_the_browser_price(): void
    {
        $waiter = $this->waiter();
        $order = $this->openTableOrder($waiter);
        $item = MenuItem::factory()->create(['price' => '250.00']);
        $cheese = new MenuItemModifier(['name' => 'Extra cheese', 'price' => '40.00', 'sort_order' => 0]);
        $item->modifiers()->save($cheese);

        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), [
            'menu_item_id' => $item->id, 'quantity' => 2, 'modifier_ids' => [$cheese->id], 'unit_price' => '1.00', 'line_total' => '1.00',
        ])->assertSessionHasNoErrors();

        $line = OrderItem::query()->firstOrFail();
        $this->assertSame('290.00', $line->unit_price);
        $this->assertSame('580.00', $line->line_total);
        $this->assertSame(OrderItemStatus::Draft, $line->status);
        $this->assertSame('580.00', $order->refresh()->subtotal);

        // Extras of another item, sold-out items and bad quantities are refused.
        $other = MenuItem::factory()->create();
        $foreign = new MenuItemModifier(['name' => 'X', 'price' => '1.00', 'sort_order' => 0]);
        $other->modifiers()->save($foreign);
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id, 'quantity' => 1, 'modifier_ids' => [$foreign->id]])
            ->assertSessionHasErrors('modifier_ids');
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->soldOut()->create()->id, 'quantity' => 1])
            ->assertSessionHasErrors('menu_item_id');
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id, 'quantity' => 0])
            ->assertSessionHasErrors('quantity');
    }

    public function test_draft_lines_can_change_but_sent_lines_cannot(): void
    {
        $waiter = $this->waiter();
        $order = $this->openTableOrder($waiter);
        $item = MenuItem::factory()->create(['price' => '100.00']);
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id, 'quantity' => 1]);
        $line = OrderItem::query()->firstOrFail();

        $this->actingAs($waiter)->patch(route('pos.items.update', $line), ['quantity' => 3])->assertSessionHasNoErrors();
        $this->assertSame('300.00', $line->refresh()->line_total);
        $this->assertSame('300.00', $order->refresh()->subtotal);

        $this->actingAs($waiter)->post(route('pos.orders.kot', $order));
        $this->actingAs($waiter)->patch(route('pos.items.update', $line), ['quantity' => 1])->assertSessionHasErrors('quantity');
        $this->actingAs($waiter)->delete(route('pos.items.destroy', $line))->assertSessionHasErrors('quantity');
        $this->assertSame(3, $line->refresh()->quantity);

        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => $item->id, 'quantity' => 1]);
        $draft = OrderItem::query()->latest('id')->firstOrFail();
        $this->actingAs($waiter)->delete(route('pos.items.destroy', $draft))->assertSessionHasNoErrors();
        $this->assertModelMissing($draft);
        $this->assertSame('300.00', $order->refresh()->subtotal);
    }

    public function test_send_kot_makes_one_ticket_per_station(): void
    {
        $waiter = $this->waiter();
        $order = $this->openTableOrder($waiter);
        $kitchen = Station::factory()->create(['name' => 'Kitchen']);
        $bar = Station::factory()->create(['name' => 'Bar']);

        foreach ([$kitchen, $kitchen, $bar] as $station) {
            $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create(['station_id' => $station->id])->id, 'quantity' => 1]);
        }

        $this->actingAs($waiter)->post(route('pos.orders.kot', $order))->assertSessionHasNoErrors();

        $this->assertSame(2, Kot::query()->count());
        $this->assertSame(0, OrderItem::query()->where('status', 'draft')->count());
        $this->assertSame(2, OrderItem::query()->where('kot_id', Kot::query()->where('station_id', $kitchen->id)->value('id'))->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.kot_sent', 'subject_id' => $order->id]);

        $this->actingAs($waiter)->post(route('pos.orders.kot', $order))->assertSessionHasErrors('order');

        $kot = Kot::query()->firstOrFail();
        $this->actingAs($waiter)->get(route('pos.kots.print', $kot))->assertOk()->assertSee('KOT #'.$kot->number)->assertDontSee('REPRINT');
        $this->assertNotNull($kot->refresh()->printed_at);
        $this->actingAs($waiter)->get(route('pos.kots.print', $kot))->assertOk()->assertSee('REPRINT');
    }

    public function test_void_needs_reason_and_manager_approval(): void
    {
        $waiter = $this->waiter();
        $manager = User::factory()->admin()->withPin('4321')->create(['username' => 'boss']);
        $order = $this->openTableOrder($waiter);
        $line = $this->addSent($order, $waiter);
        $this->assertSame('300.00', $order->refresh()->subtotal);

        $this->actingAs($waiter)->post(route('pos.items.void', $line), [])->assertSessionHasErrors('reason');
        $this->actingAs($waiter)->post(route('pos.items.void', $line), ['reason' => 'Wrong item'])->assertSessionHasErrors('approver_username');
        $this->actingAs($waiter)->post(route('pos.items.void', $line), ['reason' => 'Wrong item', 'approver_username' => 'boss', 'approver_secret' => '0000'])
            ->assertSessionHasErrors('approver_username');
        $this->assertDatabaseHas('audit_logs', ['action' => 'approval.failed']);
        // A waiter cannot approve for another waiter.
        $other = User::factory()->role(Role::Waiter)->withPin('1111')->create(['username' => 'shyam']);
        $this->actingAs($waiter)->post(route('pos.items.void', $line), ['reason' => 'Wrong item', 'approver_username' => 'shyam', 'approver_secret' => '1111'])
            ->assertSessionHasErrors('approver_username');
        $this->assertSame(OrderItemStatus::Sent, $line->refresh()->status);

        $this->actingAs($waiter)->post(route('pos.items.void', $line), ['reason' => 'Wrong item', 'approver_username' => 'BOSS', 'approver_secret' => '4321'])
            ->assertSessionHasNoErrors();

        $line->refresh();
        $this->assertSame(OrderItemStatus::Void, $line->status);
        $this->assertSame($manager->id, $line->void_approved_by);
        $this->assertSame('0.00', $order->refresh()->subtotal);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.item_voided', 'subject_id' => $line->id, 'user_id' => $waiter->id]);
        $this->assertNotNull($other->id);
    }

    public function test_managers_approve_their_own_void_and_drafts_cannot_be_voided(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->openTableOrder($admin);
        $line = $this->addSent($order, $admin);

        $this->actingAs($admin)->post(route('pos.items.void', $line), ['reason' => 'Customer left'])->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $line->refresh()->void_approved_by);

        $this->actingAs($admin)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create()->id, 'quantity' => 1]);
        $draft = OrderItem::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('pos.items.void', $draft), ['reason' => 'Test'])->assertSessionHasErrors('reason');
    }

    public function test_move_table_and_mark_served(): void
    {
        $waiter = $this->waiter();
        $from = DiningTable::factory()->create();
        $to = DiningTable::factory()->create();
        $busy = DiningTable::factory()->status(TableStatus::Occupied)->create();
        $order = $this->openTableOrder($waiter, $from);
        $line = $this->addSent($order, $waiter);

        $this->actingAs($waiter)->post(route('pos.orders.move', $order), ['dining_table_id' => $busy->id])->assertSessionHasErrors('dining_table_id');
        $this->actingAs($waiter)->post(route('pos.orders.move', $order), ['dining_table_id' => $to->id])->assertSessionHasNoErrors();

        $this->assertSame($to->id, $order->refresh()->dining_table_id);
        $this->assertSame(TableStatus::Occupied, $to->refresh()->status);
        $this->assertSame(TableStatus::Cleaning, $from->refresh()->status);

        $this->actingAs($waiter)->patch(route('pos.items.served', $line))->assertSessionHasNoErrors();
        $this->assertSame(OrderItemStatus::Served, $line->refresh()->status);
        $this->actingAs($waiter)->patch(route('pos.items.served', $line))->assertSessionHasErrors('order');
    }

    public function test_merge_two_orders(): void
    {
        $waiter = $this->waiter();
        $t1 = DiningTable::factory()->create();
        $t2 = DiningTable::factory()->create();
        $a = $this->openTableOrder($waiter, $t1);
        $b = $this->openTableOrder($waiter, $t2);
        $this->addSent($a, $waiter);
        $this->addSent($b, $waiter);

        $this->actingAs($waiter)->post(route('pos.orders.merge', $a), ['source_order_id' => $a->id])->assertSessionHasErrors('source_order_id');
        $this->actingAs($waiter)->post(route('pos.orders.merge', $a), ['source_order_id' => $b->id])->assertSessionHasNoErrors();

        $this->assertSame('600.00', $a->refresh()->subtotal);
        $this->assertSame(4, $a->guests);
        $this->assertSame(OrderStatus::Cancelled, $b->refresh()->status);
        $this->assertSame(TableStatus::Cleaning, $t2->refresh()->status);
        $this->assertSame(2, OrderItem::query()->where('order_id', $a->id)->count());
    }

    public function test_cancel_only_when_nothing_is_live_in_the_kitchen(): void
    {
        $waiter = $this->waiter();
        $table = DiningTable::factory()->create();
        $order = $this->openTableOrder($waiter, $table);
        $this->addSent($order, $waiter);

        $this->actingAs($waiter)->post(route('pos.orders.cancel', $order), ['reason' => 'Left'])->assertSessionHasErrors('reason');

        $empty = $this->openTableOrder($waiter);
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $empty), ['menu_item_id' => MenuItem::factory()->create()->id, 'quantity' => 1]);
        $this->actingAs($waiter)->post(route('pos.orders.cancel', $empty), [])->assertSessionHasErrors('reason');
        $this->actingAs($waiter)->post(route('pos.orders.cancel', $empty), ['reason' => 'Guests left'])->assertRedirect(route('pos.index'));

        $empty->refresh();
        $this->assertSame(OrderStatus::Cancelled, $empty->status);
        $this->assertSame(TableStatus::Free, $empty->table?->status);
        $this->assertSame(0, OrderItem::query()->where('order_id', $empty->id)->count());

        // Closed orders are read-only.
        $this->actingAs($waiter)->get(route('pos.orders.show', $empty))->assertRedirect(route('pos.index'));
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $empty), ['menu_item_id' => MenuItem::factory()->create()->id, 'quantity' => 1])
            ->assertSessionHasErrors('order');
    }
}
