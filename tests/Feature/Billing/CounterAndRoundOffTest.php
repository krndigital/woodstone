<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\CashBook;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterAndRoundOffTest extends TestCase
{
    use RefreshDatabase;

    private function counterOrder(string $price, int $qty = 1): Order
    {
        $cashier = User::factory()->role(Role::Cashier)->create();
        $this->actingAs($cashier)->post(route('pos.orders.store'), ['type' => 'counter'])->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($cashier)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create(['price' => $price])->id, 'quantity' => $qty]);
        $this->actingAs($cashier)->post(route('pos.orders.kot', $order));

        return $order;
    }

    public function test_counter_sale_has_a_token_and_no_table(): void
    {
        $order = $this->counterOrder('60.00');
        $this->assertNull($order->dining_table_id);
        $this->assertSame(1, $order->token);
        $this->assertSame('Counter · Token 1', $order->label());
    }

    public function test_round_off_to_the_nearest_rupee_and_bank_payment(): void
    {
        Settings::save(['vat_percent' => '13'], User::factory()->superAdmin()->create());
        $order = $this->counterOrder('99.50'); // VAT 12.935 → 12.94; 112.44 → 112.00, round off −0.44

        $this->actingAs(User::factory()->role(Role::Cashier)->create())->post(route('billing.settle', $order), ['payments' => [['method' => 'bank', 'amount' => '112.44']]])
            ->assertSessionHasErrors('payments');
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->post(route('billing.settle', $order), ['payments' => [['method' => 'bank', 'amount' => '112.00']]])
            ->assertSessionHasNoErrors();

        $bill = Bill::query()->firstOrFail();
        $this->assertSame('-0.44', $bill->round_off);
        $this->assertSame('112.00', $bill->total);
        $this->assertSame(11200, CashBook::balance(CashBook::account('bank')));
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('billing.bills.print', $bill))->assertSee('Round off')->assertSee('Date (BS)');
    }

    public function test_complimentary_needs_manager_and_needs_no_payment(): void
    {
        $order = $this->counterOrder('300.00');
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->post(route('billing.settle', $order), ['discount_type' => 'percent', 'discount_value' => '100', 'discount_reason' => 'Complimentary', 'payments' => []])
            ->assertSessionHasErrors('approver_username');
        $this->actingAs(User::factory()->admin()->create())->post(route('billing.settle', $order), ['discount_type' => 'percent', 'discount_value' => '100', 'discount_reason' => 'Complimentary', 'payments' => []])
            ->assertSessionHasNoErrors();
        $this->assertSame('0.00', Bill::query()->firstOrFail()->total);
    }

    public function test_due_receipt_and_statement_print(): void
    {
        $customer = Customer::factory()->create(['name' => 'Ram Office']);
        $due = new LedgerEntry;
        $due->forceFill(['customer_id' => $customer->id, 'type' => 'bill_due', 'amount' => '900.00', 'created_by' => User::factory()->create()->id, 'created_at' => now()])->save();
        $cashier = User::factory()->role(Role::Cashier)->create();

        $this->actingAs($cashier)->post(route('customers.payments.store', $customer), ['method' => 'cash', 'amount' => '400'])->assertSessionHasNoErrors();
        $payment = LedgerEntry::query()->where('type', 'payment')->firstOrFail();

        $this->actingAs($cashier)->get(route('customers.receipts.print', $payment))->assertOk()->assertSee('PAYMENT RECEIPT')->assertSee('400.00')->assertSee('500.00');
        $this->actingAs($cashier)->get(route('customers.receipts.print', $due))->assertNotFound();
        $this->actingAs($cashier)->get(route('customers.statement', $customer))->assertOk()->assertSee('Ram Office')->assertSee('Due now: Rs 500.00');
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->get(route('customers.statement', $customer))->assertForbidden();
    }
}
