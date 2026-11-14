<?php

declare(strict_types=1);

namespace Tests\Feature\Money;

use App\Accounting\Ledger;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\ExpenseCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CashBook;
use App\Support\Payables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private function busyDay(): void
    {
        $admin = User::factory()->admin()->create();
        // Owner puts in cash, buys stock on credit, sells, pays expense, moves cash to bank.
        $this->actingAs($admin)->post(route('money.cash-book.entries'), ['account_id' => CashBook::account('cash')->id, 'direction' => 'in', 'amount' => '5000', 'category' => 'owner_capital', 'note' => 'Start']);
        $this->actingAs($admin)->post(route('stock.suppliers.store'), ['name' => 'Meat House', 'credit_days' => 15, 'opening_balance' => '1000']);
        $supplier = Supplier::query()->firstOrFail();
        $this->actingAs($admin)->post(route('stock.items.store'), ['name' => 'Chicken', 'kind' => 'ingredient', 'unit' => 'g']);
        $chicken = StockItem::query()->firstOrFail();
        $this->actingAs($admin)->post(route('stock.purchases.store'), ['supplier_id' => $supplier->id, 'purchased_on' => now()->toDateString(), 'paid_amount' => '500', 'paid_method' => 'cash',
            'lines' => [['stock_item_id' => $chicken->id, 'quantity' => '2000', 'rate' => '1.00']]])->assertSessionHasNoErrors();
        $momo = MenuItem::factory()->create(['price' => '400.00']);
        $this->actingAs($admin)->put(route('stock.recipes.update', $momo), ['lines' => [['stock_item_id' => $chicken->id, 'quantity' => '150']]]);

        $waiter = User::factory()->role(Role::Waiter)->create();
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => DiningTable::factory()->create()->id]);
        $order = Order::query()->firstOrFail();
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => $momo->id, 'quantity' => 2]);
        $this->actingAs($waiter)->post(route('pos.orders.kot', $order));
        $customer = Customer::factory()->create();
        $this->actingAs($admin)->post(route('billing.settle', $order), ['payments' => [['method' => 'cash', 'amount' => '500.00'], ['method' => 'due', 'amount' => '300.00', 'customer_id' => $customer->id]]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('money.expenses.store'), ['expense_category_id' => ExpenseCategory::query()->where('name', 'Gas')->value('id'), 'description' => 'Cylinder', 'amount' => '200', 'account_id' => CashBook::account('cash')->id, 'spent_on' => now()->toDateString()]);
        $this->actingAs($admin)->post(route('money.cash-book.transfers'), ['from_account_id' => CashBook::account('cash')->id, 'to_account_id' => CashBook::account('bank')->id, 'amount' => '1000']);
    }

    public function test_every_journal_balances_and_balance_sheet_balances(): void
    {
        $this->busyDay();
        (new Ledger)->sync();

        $dr = (float) DB::table('journal_lines')->sum('debit');
        $cr = (float) DB::table('journal_lines')->sum('credit');
        $this->assertEqualsWithDelta($dr, $cr, 0.001);
        $this->assertSame(0, (new Ledger)->sync()); // nothing twice

        $owner = User::factory()->superAdmin()->create();
        $this->actingAs($owner)->get(route('reports.show', 'balance-sheet'))->assertOk()->assertInertia(function (Assert $page): void {
            $rows = collect($page->toArray()['props']['data']['rows'])->pluck('amount', 'line');
            $this->assertSame($rows['Total assets'], $rows['Liabilities + equity']);
            // Cash 5000 − 500 purchase + 500 sale − 200 gas − 1000 to bank = 3800
            $this->assertSame(380000, $rows['1000 Cash in hand']);
            $this->assertSame(100000, $rows['1010 Bank']);
            $this->assertSame(30000, $rows['1100 Customer receivables']);
            // Stock 2000 g − 300 g used, at Rs 1/g
            $this->assertSame(170000, $rows['1200 Stock (inventory)']);
            // Opening 1000 + purchase 2000 − paid 500
            $this->assertSame(250000, $rows['2000 Supplier payables']);
        });
        $this->actingAs($owner)->get(route('reports.show', 'trial-balance'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('data.totals.debit', fn ($v) => $v > 0));
        $this->actingAs($owner)->get(route('reports.show', 'cash-flow'))->assertOk();
        $this->actingAs($owner)->get(route('accounting.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('accounting/Index')->has('journals'));
        $this->actingAs(User::factory()->admin()->create())->get(route('accounting.index'))->assertForbidden();
    }

    public function test_manual_journal_must_balance(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $this->actingAs($owner)->post(route('accounting.journals.store'), ['entry_date' => now()->toDateString(), 'memo' => 'Fix', 'lines' => [['code' => '1000', 'debit' => '10'], ['code' => '6990', 'credit' => '9']]])
            ->assertSessionHasErrors('lines');
        $this->actingAs($owner)->post(route('accounting.journals.store'), ['entry_date' => now()->toDateString(), 'memo' => 'Fix', 'lines' => [['code' => '1000', 'debit' => '10'], ['code' => 'nope', 'credit' => '10']]])
            ->assertSessionHasErrors('lines.1.code');
        $this->actingAs($owner)->post(route('accounting.journals.store'), ['entry_date' => now()->toDateString(), 'memo' => 'Fix', 'lines' => [['code' => '6990', 'debit' => '10'], ['code' => '1000', 'credit' => '10']]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('journal_entries', ['source_type' => 'manual', 'memo' => 'Fix']);
        $this->actingAs(User::factory()->admin()->create())->post(route('accounting.journals.store'), [])->assertForbidden();
    }

    public function test_payables_ageing_and_opening_balances(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('stock.suppliers.store'), ['name' => 'Dairy', 'credit_days' => 10, 'opening_balance' => '700']);
        $supplier = Supplier::query()->firstOrFail();
        $this->actingAs($admin)->post(route('stock.items.store'), ['name' => 'Milk', 'kind' => 'ingredient', 'unit' => 'ml']);
        $this->actingAs($admin)->post(route('stock.purchases.store'), ['supplier_id' => $supplier->id, 'purchased_on' => now()->subDays(40)->toDateString(),
            'lines' => [['stock_item_id' => StockItem::query()->value('id'), 'quantity' => '1000', 'rate' => '0.50']]])->assertSessionHasNoErrors();

        $row = Payables::ageing()[$supplier->id];
        $this->assertSame(120000, $row['owed']);
        $this->assertSame(50000, $row['d1_30']); // due 30 days ago
        $this->assertSame(70000, $row['not_due']); // opening, due today

        $this->actingAs($admin)->post(route('customers.store'), ['name' => 'Old guest', 'opening_balance' => '450'])->assertSessionHasNoErrors();
        $this->assertSame(45000, Customer::query()->where('name', 'Old guest')->firstOrFail()->balancePaisa());
        $this->actingAs($admin)->get(route('reports.show', 'payables-ageing'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('data.totals.owed', 120000));
    }
}
