<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Enums\PurchaseOrderStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private function keeper(): User
    {
        return User::factory()->role(Role::StoreKeeper)->create();
    }

    private function chicken(): StockItem
    {
        $this->actingAs($this->keeper())->post(route('stock.items.store'), [
            'name' => 'Chicken', 'kind' => 'ingredient', 'unit' => 'g', 'purchase_unit' => 'kg', 'purchase_factor' => '1000', 'reorder_level' => '2000',
        ])->assertSessionHasNoErrors();

        return StockItem::query()->where('name', 'Chicken')->firstOrFail();
    }

    private function supplier(): Supplier
    {
        $this->actingAs($this->keeper())->post(route('stock.suppliers.store'), ['name' => 'Butwal Meat', 'pan' => '123456789'])->assertSessionHasNoErrors();

        return Supplier::query()->firstOrFail();
    }

    public function test_access_by_role(): void
    {
        foreach (['stock.items.index', 'stock.purchases.index', 'stock.orders.index', 'stock.suppliers.index', 'stock.recipes.index'] as $name) {
            $this->app['auth']->forgetGuards();
            $this->get(route($name))->assertRedirect(route('login'));
            $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route($name))->assertForbidden();
            $this->actingAs($this->keeper())->get(route($name))->assertOk();
        }
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->post(route('stock.items.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_item_validation(): void
    {
        $this->actingAs($this->keeper())->post(route('stock.items.store'), ['name' => '', 'kind' => 'gold', 'unit' => ''])
            ->assertSessionHasErrors(['name', 'kind', 'unit']);
        $this->actingAs($this->keeper())->post(route('stock.items.store'), ['name' => 'Milk', 'kind' => 'ingredient', 'unit' => 'ml', 'purchase_unit' => 'ltr'])
            ->assertSessionHasErrors('purchase_factor');
    }

    public function test_purchase_in_kg_adds_grams_and_updates_average_cost(): void
    {
        $chicken = $this->chicken();
        $supplier = $this->supplier();
        $keeper = $this->keeper();

        // 5 kg at Rs 600/kg → 5000 g at Rs 0.60/g
        $this->actingAs($keeper)->post(route('stock.purchases.store'), [
            'supplier_id' => $supplier->id, 'purchased_on' => now()->toDateString(), 'paid_amount' => '1000', 'paid_method' => 'cash',
            'lines' => [['stock_item_id' => $chicken->id, 'quantity' => '5', 'in_purchase_unit' => true, 'rate' => '600']],
        ])->assertSessionHasNoErrors();

        $chicken->refresh();
        $this->assertSame('5000.000', $chicken->on_hand);
        $this->assertSame('0.6000', $chicken->avg_cost);
        $purchase = Purchase::query()->firstOrFail();
        $this->assertSame('3000.00', $purchase->total);
        $this->assertSame('1000.00', $purchase->paid_amount);

        // 5 kg more at Rs 700/kg → average Rs 0.65/g
        $this->actingAs($keeper)->post(route('stock.purchases.store'), [
            'supplier_id' => $supplier->id, 'purchased_on' => now()->toDateString(),
            'lines' => [['stock_item_id' => $chicken->id, 'quantity' => '5', 'in_purchase_unit' => true, 'rate' => '700']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('0.6500', $chicken->refresh()->avg_cost);
        $this->assertSame('10000.000', $chicken->on_hand);

        $this->actingAs($keeper)->get(route('stock.suppliers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('suppliers.0.owed', '5500.00'));
    }

    public function test_purchase_validation(): void
    {
        $chicken = $this->chicken();
        $supplier = $this->supplier();

        $this->actingAs($this->keeper())->post(route('stock.purchases.store'), ['supplier_id' => $supplier->id, 'purchased_on' => now()->toDateString(), 'lines' => []])
            ->assertSessionHasErrors('lines');
        $this->actingAs($this->keeper())->post(route('stock.purchases.store'), [
            'supplier_id' => $supplier->id, 'purchased_on' => now()->addDay()->toDateString(),
            'lines' => [['stock_item_id' => $chicken->id, 'quantity' => '1', 'rate' => '600']],
        ])->assertSessionHasErrors('purchased_on');
        $this->actingAs($this->keeper())->post(route('stock.purchases.store'), [
            'supplier_id' => $supplier->id, 'purchased_on' => now()->toDateString(), 'paid_amount' => '700',
            'lines' => [['stock_item_id' => $chicken->id, 'quantity' => '1', 'in_purchase_unit' => true, 'rate' => '600']],
        ])->assertSessionHasErrors('paid_amount');
        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_purchase_order_is_received_with_differences_flagged(): void
    {
        $chicken = $this->chicken();
        $supplier = $this->supplier();
        $keeper = $this->keeper();

        $this->actingAs($keeper)->post(route('stock.orders.store'), [
            'supplier_id' => $supplier->id, 'lines' => [['stock_item_id' => $chicken->id, 'quantity' => '10', 'expected_rate' => '600']],
        ])->assertSessionHasNoErrors();
        $po = PurchaseOrder::query()->firstOrFail();
        $this->assertSame(PurchaseOrderStatus::Draft, $po->status);

        $this->actingAs($keeper)->patch(route('stock.orders.status', $po), ['status' => 'sent'])->assertSessionHasNoErrors();
        $this->actingAs($keeper)->get(route('stock.orders.print', $po))->assertOk()->assertSee('PURCHASE ORDER')->assertSee('Chicken');
        $line = $po->lines()->firstOrFail();

        // Receive 6 kg at a higher rate → partial, differences flagged.
        $this->actingAs($keeper)->post(route('stock.purchases.store'), [
            'supplier_id' => $supplier->id, 'purchase_order_id' => $po->id, 'purchased_on' => now()->toDateString(),
            'lines' => [['stock_item_id' => $chicken->id, 'quantity' => '6', 'in_purchase_unit' => true, 'rate' => '620', 'po_line_id' => $line->id]],
        ])->assertSessionHasNoErrors();
        $log = AuditLog::query()->where('action', 'stock.purchase_recorded')->latest('id')->firstOrFail();
        $this->assertCount(2, (array) ($log->properties['differences'] ?? []));

        $this->assertSame(PurchaseOrderStatus::Partial, $po->refresh()->status);
        $this->assertSame('6.000', $line->refresh()->received_quantity);

        $this->actingAs($keeper)->post(route('stock.purchases.store'), [
            'supplier_id' => $supplier->id, 'purchase_order_id' => $po->id, 'purchased_on' => now()->toDateString(),
            'lines' => [['stock_item_id' => $chicken->id, 'quantity' => '4', 'in_purchase_unit' => true, 'rate' => '600', 'po_line_id' => $line->id]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(PurchaseOrderStatus::Received, $po->refresh()->status);
        $this->assertSame('10000.000', $chicken->refresh()->on_hand);

        $this->actingAs($keeper)->patch(route('stock.orders.status', $po), ['status' => 'cancelled'])->assertSessionHasErrors('status');
    }

    public function test_wastage_count_and_opening_entries(): void
    {
        $chicken = $this->chicken();
        $keeper = $this->keeper();

        $this->actingAs($keeper)->post(route('stock.items.adjust', $chicken), ['type' => 'opening', 'quantity' => '3000', 'unit_cost' => '0.5', 'reason' => 'Go-live'])
            ->assertSessionHasNoErrors();
        $this->assertSame('3000.000', $chicken->refresh()->on_hand);
        $this->assertSame('0.5000', $chicken->avg_cost);

        $this->actingAs($keeper)->post(route('stock.items.adjust', $chicken), ['type' => 'wastage', 'quantity' => '200', 'reason' => 'Spoiled'])->assertSessionHasNoErrors();
        $this->assertSame('2800.000', $chicken->refresh()->on_hand);

        $this->actingAs($keeper)->post(route('stock.items.adjust', $chicken), ['type' => 'count', 'quantity' => '2750', 'reason' => 'Night count'])->assertSessionHasNoErrors();
        $this->assertSame('2750.000', $chicken->refresh()->on_hand);
        $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $chicken->id, 'type' => 'count', 'quantity' => '-50.000']);

        $this->actingAs($keeper)->post(route('stock.items.adjust', $chicken), ['type' => 'sale', 'quantity' => '1', 'reason' => 'Nope'])->assertSessionHasErrors('type');
        $this->actingAs($keeper)->post(route('stock.items.adjust', $chicken), ['type' => 'wastage', 'quantity' => '1'])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.wastage', 'subject_id' => $chicken->id]);

        $this->actingAs($keeper)->get(route('stock.items.index', ['item' => $chicken->id]))
            ->assertInertia(fn (Assert $page) => $page->where('items.0.low', false));

        $this->expectException(LogicException::class);
        StockMovement::query()->firstOrFail()->delete();
    }

    public function test_paid_bill_deducts_recipe_ingredients(): void
    {
        $chicken = $this->chicken();
        $keeper = $this->keeper();
        $this->actingAs($keeper)->post(route('stock.items.adjust', $chicken), ['type' => 'opening', 'quantity' => '5000', 'unit_cost' => '0.6', 'reason' => 'Go-live']);
        $momo = MenuItem::factory()->create(['price' => '250.00']);

        $this->actingAs($keeper)->put(route('stock.recipes.update', $momo), ['lines' => [['stock_item_id' => $chicken->id, 'quantity' => '150']]])->assertSessionHasNoErrors();
        $this->actingAs($keeper)->get(route('stock.recipes.index'))
            ->assertInertia(fn (Assert $page) => $page->where('menuItems.0.cost', '90.00')->where('menuItems.0.margin', 64));

        $waiter = User::factory()->role(Role::Waiter)->create();
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => DiningTable::factory()->create()->id]);
        $order = Order::query()->firstOrFail();
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => $momo->id, 'quantity' => 2]);
        $this->actingAs($waiter)->post(route('pos.orders.kot', $order));
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->post(route('billing.settle', $order), ['payments' => [['method' => 'cash', 'amount' => '500.00']]])
            ->assertSessionHasNoErrors();

        $this->assertSame('4700.000', $chicken->refresh()->on_hand);
        $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $chicken->id, 'type' => 'sale', 'quantity' => '-300.000', 'reference_type' => 'bill']);
    }
}
