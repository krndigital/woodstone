<?php

declare(strict_types=1);

namespace Tests\Feature\Money;

use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CashBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    private function cash(): Account
    {
        return CashBook::account('cash');
    }

    private function paidBill(string $method = 'cash', ?Customer $customer = null): Bill
    {
        $waiter = User::factory()->role(Role::Waiter)->create();
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => DiningTable::factory()->create()->id]);
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create(['price' => '500.00'])->id, 'quantity' => 2]);
        $this->actingAs($waiter)->post(route('pos.orders.kot', $order));
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->post(route('billing.settle', $order), [
            'payments' => [['method' => $method, 'amount' => '1000.00', 'qr_checked' => true, 'customer_id' => $customer?->id]],
        ])->assertSessionHasNoErrors();

        return Bill::query()->latest('id')->firstOrFail();
    }

    public function test_access_by_role(): void
    {
        $this->get(route('money.cash-book'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('money.cash-book'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->get(route('money.expenses.index'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->get(route('money.day'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('money.expenses.index'))->assertOk();
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('money.day'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('money.cash-book'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('money/CashBook')->has('accounts', 4));
    }

    public function test_sales_and_due_receipts_post_to_the_right_accounts(): void
    {
        $this->paidBill('cash');
        $this->paidBill('qr');
        $customer = Customer::factory()->create();
        $this->paidBill('due', $customer);

        $this->assertSame(100000, CashBook::balance($this->cash()));
        $this->assertSame(100000, CashBook::balance(CashBook::account('qr')));

        $this->actingAs(User::factory()->role(Role::Cashier)->create())->post(route('customers.payments.store', $customer), ['method' => 'cash', 'amount' => '400.00']);
        $this->assertSame(140000, CashBook::balance($this->cash()));
    }

    public function test_expense_posts_cash_out_and_void_reverses_it(): void
    {
        Storage::fake('local');
        $cashier = User::factory()->role(Role::Cashier)->create();
        $gas = ExpenseCategory::query()->where('name', 'Gas')->firstOrFail();

        $this->actingAs($cashier)->post(route('money.expenses.store'), ['expense_category_id' => $gas->id, 'description' => 'Cylinder', 'amount' => '0', 'account_id' => $this->cash()->id, 'spent_on' => now()->toDateString()])
            ->assertSessionHasErrors('amount');
        $this->actingAs($cashier)->post(route('money.expenses.store'), [
            'expense_category_id' => $gas->id, 'description' => 'Cylinder', 'amount' => '1950', 'account_id' => $this->cash()->id,
            'spent_on' => now()->toDateString(), 'receipt' => UploadedFile::fake()->image('r.jpg'),
        ])->assertSessionHasNoErrors();

        $expense = Expense::query()->firstOrFail();
        $this->assertSame(-195000, CashBook::balance($this->cash()));
        $this->assertNotNull($expense->receipt_path);
        $this->actingAs($cashier)->get(route('money.expenses.receipt', $expense))->assertOk();

        $this->actingAs($cashier)->patch(route('money.expenses.void', $expense), ['reason' => 'Wrong'])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->patch(route('money.expenses.void', $expense), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();
        $this->assertSame(0, CashBook::balance($this->cash()));
        $this->assertNotNull($expense->refresh()->voided_at);

        $this->expectException(LogicException::class);
        $expense->forceFill(['amount' => '1.00'])->save();
    }

    public function test_recurring_expense_shows_due_and_is_confirmed_once_per_month(): void
    {
        $admin = User::factory()->admin()->create();
        $rent = ExpenseCategory::query()->where('name', 'Rent')->firstOrFail();
        $this->actingAs($admin)->post(route('money.recurring.store'), ['expense_category_id' => $rent->id, 'description' => 'Shop rent', 'amount' => '25000', 'day_of_month' => 1])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('money.expenses.index'))->assertInertia(fn (Assert $page) => $page->where('recurring.0.due', true));
        $this->actingAs($admin)->post(route('money.expenses.store'), [
            'expense_category_id' => $rent->id, 'description' => 'Shop rent', 'amount' => '25000', 'account_id' => CashBook::account('bank')->id,
            'spent_on' => now()->toDateString(), 'recurring_id' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('money.expenses.index'))->assertInertia(fn (Assert $page) => $page->where('recurring.0.due', false));
    }

    public function test_manual_entry_and_transfer(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('money.cash-book.entries'), ['account_id' => $this->cash()->id, 'direction' => 'in', 'amount' => '5000', 'category' => 'owner_capital', 'note' => 'Float'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('money.cash-book.transfers'), ['from_account_id' => $this->cash()->id, 'to_account_id' => $this->cash()->id, 'amount' => '1'])
            ->assertSessionHasErrors('to_account_id');
        $this->actingAs($admin)->post(route('money.cash-book.transfers'), ['from_account_id' => $this->cash()->id, 'to_account_id' => CashBook::account('bank')->id, 'amount' => '3000'])
            ->assertSessionHasNoErrors();

        $this->assertSame(200000, CashBook::balance($this->cash()));
        $this->assertSame(300000, CashBook::balance(CashBook::account('bank')));
        $this->expectException(LogicException::class);
        AccountTransaction::query()->firstOrFail()->delete();
    }

    public function test_day_open_and_close_posts_the_difference(): void
    {
        $cashier = User::factory()->role(Role::Cashier)->create();
        $this->actingAs($cashier)->post(route('money.day.close'), ['counted_cash' => '100'])->assertSessionHasErrors('counted_cash');
        $this->actingAs($cashier)->post(route('money.day.open'), ['opening_cash' => '2000'])->assertSessionHasNoErrors();
        $this->actingAs($cashier)->post(route('money.day.open'), ['opening_cash' => '2000'])->assertSessionHasErrors('opening_cash');

        $this->paidBill('cash'); // +1000

        $this->actingAs($cashier)->post(route('money.day.close'), ['counted_cash' => '2950', 'note' => 'Short'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('day_closes', ['expected_cash' => '3000.00', 'counted_cash' => '2950.00', 'difference' => '-50.00']);
        $this->assertSame(95000, CashBook::balance($this->cash()));
    }

    public function test_supplier_payment_and_purchase_return_reduce_what_we_owe(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = new Supplier(['name' => 'Dairy']);
        $supplier->forceFill(['is_active' => true])->save();
        $milk = new StockItem(['name' => 'Milk', 'kind' => 'ingredient', 'unit' => 'ml', 'reorder_level' => '0']);
        $milk->forceFill(['is_active' => true, 'on_hand' => '0', 'avg_cost' => '0'])->save();

        $this->actingAs($admin)->post(route('stock.purchases.store'), [
            'supplier_id' => $supplier->id, 'purchased_on' => now()->toDateString(), 'paid_amount' => '200', 'paid_method' => 'cash',
            'lines' => [['stock_item_id' => $milk->id, 'quantity' => '10000', 'rate' => '0.10']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(-20000, CashBook::balance($this->cash()));

        $this->actingAs($admin)->post(route('stock.suppliers.payments.store', $supplier), ['amount' => '900', 'account_id' => $this->cash()->id, 'paid_on' => now()->toDateString()])
            ->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('stock.suppliers.payments.store', $supplier), ['amount' => '500', 'account_id' => $this->cash()->id, 'paid_on' => now()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('stock.suppliers.returns.store', $supplier), ['stock_item_id' => $milk->id, 'quantity' => '1000', 'amount' => '100', 'reason' => 'Sour'])
            ->assertSessionHasNoErrors();

        $this->assertSame('9000.000', $milk->refresh()->on_hand);
        $this->actingAs($admin)->get(route('stock.suppliers.index'))->assertInertia(fn (Assert $page) => $page->where('suppliers.0.owed', '200.00'));
        $this->actingAs(User::factory()->role(Role::StoreKeeper)->create())
            ->post(route('stock.suppliers.payments.store', $supplier), ['amount' => '1', 'account_id' => $this->cash()->id, 'paid_on' => now()->toDateString()])
            ->assertForbidden();
    }

    public function test_bill_return_needs_approval_and_refunds(): void
    {
        $bill = $this->paidBill('cash');
        $cashier = User::factory()->role(Role::Cashier)->create();
        User::factory()->admin()->create(['username' => 'boss']);

        $this->actingAs($cashier)->post(route('billing.bills.return', $bill), ['full_cancel' => true, 'refund_method' => 'cash', 'reason' => 'Wrong table'])
            ->assertSessionHasErrors('approver_username');
        $this->actingAs($cashier)->post(route('billing.bills.return', $bill), ['full_cancel' => false, 'amount' => '200', 'refund_method' => 'cash', 'restock' => true, 'reason' => 'Cold food', 'approver_username' => 'boss', 'approver_secret' => 'password'])
            ->assertSessionHasErrors('restock');
        $this->actingAs($cashier)->post(route('billing.bills.return', $bill), ['full_cancel' => false, 'amount' => '200', 'refund_method' => 'cash', 'reason' => 'Cold food', 'approver_username' => 'boss', 'approver_secret' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertSame(80000, CashBook::balance($this->cash()));

        $this->actingAs(User::factory()->admin()->create())->post(route('billing.bills.return', $bill), ['full_cancel' => true, 'refund_method' => 'cash', 'reason' => 'Guest left'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, CashBook::balance($this->cash()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'bill.cancelled', 'subject_id' => $bill->id]);
        $this->actingAs(User::factory()->admin()->create())->post(route('billing.bills.return', $bill), ['full_cancel' => true, 'refund_method' => 'cash', 'reason' => 'Again'])
            ->assertSessionHasErrors('amount');
        $this->assertSame('1000.00', $bill->refresh()->total);
    }

    public function test_takeaway_gets_a_token(): void
    {
        $waiter = User::factory()->role(Role::Waiter)->create();
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'takeaway']);
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'takeaway']);
        $this->assertSame([1, 2], Order::query()->orderBy('id')->pluck('token')->all());
    }
}
