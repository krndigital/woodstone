<?php

declare(strict_types=1);

namespace Tests\Feature\Floor;

use App\Accounting\Ledger;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\CashBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_with_advance_is_seated_and_used_on_the_bill(): void
    {
        $table = DiningTable::factory()->create(['code' => 'C1']);
        $cashier = User::factory()->role(Role::Cashier)->create();
        $waiter = User::factory()->role(Role::Waiter)->create();

        $this->get(route('floor.reservations.index'))->assertRedirect(route('login'));
        $this->actingAs($waiter)->post(route('floor.reservations.store'), ['dining_table_id' => $table->id, 'name' => 'Hari', 'reserved_at' => now()->addHour()->format('Y-m-d\TH:i'), 'guests' => 4, 'advance' => '500', 'advance_method' => 'cash'])
            ->assertForbidden(); // advance money needs a cashier
        $this->actingAs($cashier)->post(route('floor.reservations.store'), ['dining_table_id' => $table->id, 'name' => 'Hari', 'reserved_at' => now()->subHour()->format('Y-m-d\TH:i'), 'guests' => 4])
            ->assertSessionHasErrors('reserved_at');
        $at = now()->addMinutes(30);
        $this->actingAs($cashier)->post(route('floor.reservations.store'), ['dining_table_id' => $table->id, 'name' => 'Hari', 'phone' => '9800000000', 'reserved_at' => $at->format('Y-m-d\TH:i'), 'guests' => 4, 'advance' => '500', 'advance_method' => 'cash'])
            ->assertSessionHasNoErrors();
        $this->actingAs($cashier)->post(route('floor.reservations.store'), ['dining_table_id' => $table->id, 'name' => 'Sita', 'reserved_at' => $at->addHour()->format('Y-m-d\TH:i'), 'guests' => 2])
            ->assertSessionHasErrors('reserved_at'); // clash

        $this->assertSame(50000, CashBook::balance(CashBook::account('cash')));
        if ($at->isToday()) {
            $this->assertSame(TableStatus::Reserved, $table->refresh()->status);
        }
        $this->actingAs($waiter)->get(route('floor.reservations.index'))->assertInertia(fn (Assert $page) => $page->component('floor/Reservations')->has('reservations', 1));

        $r = Reservation::query()->firstOrFail();
        $this->actingAs($waiter)->post(route('floor.reservations.seat', $r))->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame('seated', $r->refresh()->status);
        $this->assertSame($order->id, $r->order_id);

        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create(['price' => '800.00'])->id, 'quantity' => 1]);
        $this->actingAs($waiter)->post(route('pos.orders.kot', $order));
        $this->actingAs($cashier)->post(route('billing.settle', $order), ['payments' => [['method' => 'advance', 'amount' => '600.00'], ['method' => 'cash', 'amount' => '200.00']]])
            ->assertSessionHasErrors('payments');
        $this->actingAs($cashier)->post(route('billing.settle', $order), ['payments' => [['method' => 'advance', 'amount' => '500.00'], ['method' => 'cash', 'amount' => '300.00']]])
            ->assertSessionHasNoErrors();
        $this->assertSame('500.00', $r->refresh()->advance_used);
        $this->assertSame(80000, CashBook::balance(CashBook::account('cash')));

        // Ledger: the advance is a liability until used; then it is cleared.
        (new Ledger)->sync();
        $bal = collect((new Ledger)->balances())->firstWhere('code', '2200');
        $this->assertSame(0, $bal['balance'] ?? 0);
    }

    public function test_no_show_keeps_or_refunds_the_advance(): void
    {
        $cashier = User::factory()->role(Role::Cashier)->create();
        foreach (['A', 'B'] as $i => $name) {
            $this->actingAs($cashier)->post(route('floor.reservations.store'), ['dining_table_id' => DiningTable::factory()->create()->id, 'name' => $name, 'reserved_at' => now()->addDays(2 + $i)->format('Y-m-d\TH:i'), 'guests' => 2, 'advance' => '300', 'advance_method' => 'cash']);
        }
        [$a, $b] = Reservation::query()->orderBy('id')->get()->all();
        $this->actingAs($cashier)->post(route('floor.reservations.close', $a), ['status' => 'no_show'])->assertSessionHasNoErrors();
        $this->actingAs($cashier)->post(route('floor.reservations.close', $b), ['status' => 'cancelled', 'refund_method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame(30000, CashBook::balance(CashBook::account('cash')));

        (new Ledger)->sync();
        $income = collect((new Ledger)->balances())->firstWhere('code', '4950');
        $this->assertSame(-30000, $income['balance'] ?? 0);
        $this->actingAs($cashier)->post(route('floor.reservations.close', $a), ['status' => 'cancelled'])->assertSessionHasErrors('reservation');
    }

    public function test_item_history_kitchen_times_and_call_routing(): void
    {
        $waiterA = User::factory()->role(Role::Waiter)->create();
        $waiterB = User::factory()->role(Role::Waiter)->create();
        $table = DiningTable::factory()->create();
        $this->actingAs($waiterA)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => $table->id]);
        $order = Order::query()->firstOrFail();
        $this->actingAs($waiterA)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create()->id, 'quantity' => 1]);
        $this->actingAs($waiterA)->post(route('pos.orders.kot', $order));
        $line = OrderItem::query()->firstOrFail();
        $cook = User::factory()->role(Role::Kitchen)->create();
        $this->actingAs($cook)->patch(route('kitchen.items.bump', $line));
        $this->actingAs($cook)->patch(route('kitchen.items.bump', $line));
        $this->actingAs($waiterA)->patch(route('pos.items.served', $line));
        $this->assertSame(['sent', 'preparing', 'ready', 'served'], DB::table('order_item_events')->where('order_item_id', $line->id)->orderBy('id')->pluck('status')->all());

        $this->actingAs(User::factory()->admin()->create())->get(route('reports.show', 'kitchen-times'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('data.totals.items', 1));

        // A guest call goes to the table's own waiter first; others see it after 60 s.
        $this->app['auth']->forgetGuards();
        $this->post(route('guest.calls.store', $table->qr_token), ['type' => 'water']);
        $this->actingAs($waiterA)->getJson(route('calls.pending'))->assertJsonCount(1, 'calls');
        $this->actingAs($waiterB)->getJson(route('calls.pending'))->assertJsonCount(0, 'calls');
        ServiceCall::query()->update(['created_at' => now()->subMinutes(2)]);
        $this->actingAs($waiterB)->getJson(route('calls.pending'))->assertJsonCount(1, 'calls');
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->getJson(route('calls.pending'))->assertJsonCount(1, 'calls');
        $this->actingAs(User::factory()->admin()->create())->get(route('reports.show', 'service-calls'))->assertOk();
    }
}
