<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function sentOrder(string $price = '500.00', int $qty = 2): Order
    {
        $waiter = User::factory()->role(Role::Waiter)->create();
        $table = DiningTable::factory()->create();
        $this->actingAs($waiter)->post(route('pos.orders.store'), ['type' => 'dine_in', 'dining_table_id' => $table->id, 'guests' => 2]);
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create(['price' => $price])->id, 'quantity' => $qty]);
        $this->actingAs($waiter)->post(route('pos.orders.kot', $order));

        return $order->refresh();
    }

    private function cashier(): User
    {
        return User::factory()->role(Role::Cashier)->create();
    }

    public function test_access_by_role(): void
    {
        $order = $this->sentOrder();
        $this->app['auth']->forgetGuards();

        $this->get(route('billing.checkout', $order))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->get(route('billing.checkout', $order))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->post(route('billing.settle', $order), ['payments' => []])->assertForbidden();
        $this->actingAs($this->cashier())->get(route('billing.checkout', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('billing/Checkout')->where('settings.vat_percent', '0')->where('settings.service_percent', '0'));
        $this->actingAs($this->cashier())->get(route('billing.index'))->assertOk();
    }

    public function test_cash_payment_creates_a_numbered_bill_and_frees_the_table(): void
    {
        $order = $this->sentOrder();
        $cashier = $this->cashier();

        $this->actingAs($cashier)->post(route('billing.settle', $order), [
            'payments' => [['method' => 'cash', 'amount' => '1000.00']],
        ])->assertSessionHasNoErrors()->assertRedirect(route('pos.index'));

        $bill = Bill::query()->firstOrFail();
        $this->assertSame('2083/84-00001', $bill->number);
        $this->assertSame('1000.00', $bill->total);
        $this->assertSame('0.00', $bill->vat_amount);
        $this->assertSame('0.00', $bill->service_amount);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(TableStatus::Cleaning, $order->diningTable?->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bill.settled', 'subject_id' => $bill->id]);

        // Billing twice is impossible.
        $this->actingAs($cashier)->post(route('billing.settle', $order), ['payments' => [['method' => 'cash', 'amount' => '1000.00']]])
            ->assertSessionHasErrors('order');
        $this->assertSame(1, Bill::query()->count());
    }

    public function test_payments_must_match_the_server_total(): void
    {
        $order = $this->sentOrder();

        $this->actingAs($this->cashier())->post(route('billing.settle', $order), ['payments' => [['method' => 'cash', 'amount' => '999.00']]])
            ->assertSessionHasErrors('payments');
        $this->actingAs($this->cashier())->post(route('billing.settle', $order), ['payments' => [['method' => 'bitcoin', 'amount' => '1000.00']]])
            ->assertSessionHasErrors('payments.0.method');
        $this->actingAs($this->cashier())->post(route('billing.settle', $order), ['payments' => [['method' => 'cash', 'amount' => '1000.001']]])
            ->assertSessionHasErrors('payments.0.amount');
        $this->assertSame(0, Bill::query()->count());
    }

    public function test_service_charge_and_vat_from_settings_and_qr_needs_the_tick(): void
    {
        Settings::save(['service_charge_percent' => '10', 'vat_percent' => '13'], User::factory()->superAdmin()->create());
        $order = $this->sentOrder();
        $cashier = $this->cashier();

        // 1000 + 100 service + 143 VAT = 1243
        $this->actingAs($cashier)->post(route('billing.settle', $order), ['payments' => [['method' => 'qr', 'amount' => '1243.00']]])
            ->assertSessionHasErrors('payments.0.qr_checked');

        $this->actingAs($cashier)->post(route('billing.settle', $order), ['payments' => [['method' => 'qr', 'amount' => '1243.00', 'qr_checked' => true, 'reference' => 'FP123']]])
            ->assertSessionHasNoErrors();

        $bill = Bill::query()->firstOrFail();
        $this->assertSame('100.00', $bill->service_amount);
        $this->assertSame('143.00', $bill->vat_amount);
        $this->assertDatabaseHas('bill_payments', ['bill_id' => $bill->id, 'method' => 'qr', 'qr_checked' => true, 'reference' => 'FP123']);
    }

    public function test_discount_needs_reason_and_approval_above_the_limit(): void
    {
        $order = $this->sentOrder();
        $cashier = $this->cashier();
        User::factory()->admin()->withPin('9999')->create(['username' => 'manager']);

        $this->actingAs($cashier)->post(route('billing.settle', $order), [
            'discount_type' => 'percent', 'discount_value' => '10', 'payments' => [['method' => 'cash', 'amount' => '900.00']],
        ])->assertSessionHasErrors('discount_reason');

        $this->actingAs($cashier)->post(route('billing.settle', $order), [
            'discount_type' => 'percent', 'discount_value' => '20', 'discount_reason' => 'Regular', 'payments' => [['method' => 'cash', 'amount' => '800.00']],
        ])->assertSessionHasErrors('approver_username');

        $this->actingAs($cashier)->post(route('billing.settle', $order), [
            'discount_type' => 'percent', 'discount_value' => '20', 'discount_reason' => 'Regular', 'payments' => [['method' => 'cash', 'amount' => '800.00']],
            'approver_username' => 'manager', 'approver_secret' => '9999',
        ])->assertSessionHasNoErrors();

        $bill = Bill::query()->firstOrFail();
        $this->assertSame('200.00', $bill->discount_amount);
        $this->assertSame('800.00', $bill->total);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bill.discount', 'subject_id' => $bill->id]);
    }

    public function test_discount_within_limit_needs_no_approval(): void
    {
        $order = $this->sentOrder();

        $this->actingAs($this->cashier())->post(route('billing.settle', $order), [
            'discount_type' => 'amount', 'discount_value' => '100', 'discount_reason' => 'Staff', 'payments' => [['method' => 'cash', 'amount' => '900.00']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('100.00', Bill::query()->firstOrFail()->discount_amount);
    }

    public function test_group_split_with_cash_qr_and_due_prints_due_account(): void
    {
        $order = $this->sentOrder('400.00', 3); // 1200
        $cashier = $this->cashier();
        $ram = Customer::factory()->create(['name' => 'Ram Office']);

        $this->actingAs($cashier)->post(route('billing.settle', $order), [
            'payments' => [
                ['payer' => 'Person 1', 'method' => 'cash', 'amount' => '400.00'],
                ['payer' => 'Person 2', 'method' => 'qr', 'amount' => '400.00', 'qr_checked' => true],
                ['payer' => 'Person 3', 'method' => 'due', 'amount' => '400.00'],
            ],
        ])->assertSessionHasErrors('payments.2.customer_id');

        $this->actingAs($cashier)->post(route('billing.settle', $order), [
            'payments' => [
                ['payer' => 'Person 1', 'method' => 'cash', 'amount' => '400.00'],
                ['payer' => 'Person 2', 'method' => 'qr', 'amount' => '400.00', 'qr_checked' => true],
                ['payer' => 'Person 3', 'method' => 'due', 'amount' => '400.00', 'customer_id' => $ram->id],
            ],
        ])->assertSessionHasNoErrors();

        $bill = Bill::query()->firstOrFail();
        $this->assertSame('800.00', $bill->paid_amount);
        $this->assertSame('400.00', $bill->due_amount);
        $this->assertSame(40000, $ram->balancePaisa());

        $this->actingAs($cashier)->get(route('billing.bills.print', $bill))
            ->assertOk()
            ->assertSee('PAID NOW')->assertSee('ON DUE')->assertSee('DUE ACCOUNT')->assertSee('Ram Office')
            ->assertSee('Service charge (0%)')->assertSee('VAT (0%)')
            ->assertDontSee('COPY');
        $this->actingAs($cashier)->get(route('billing.bills.print', $bill))->assertOk()->assertSee('COPY');
        $this->assertDatabaseHas('audit_logs', ['action' => 'bill.reprinted', 'subject_id' => $bill->id]);
    }

    public function test_due_over_the_credit_limit_needs_approval(): void
    {
        $order = $this->sentOrder();
        $customer = Customer::factory()->create(['credit_limit' => '500.00']);

        $this->actingAs($this->cashier())->post(route('billing.settle', $order), [
            'payments' => [['method' => 'due', 'amount' => '1000.00', 'customer_id' => $customer->id]],
        ])->assertSessionHasErrors('approver_username');

        $this->actingAs(User::factory()->admin()->create())->post(route('billing.settle', $order), [
            'payments' => [['method' => 'due', 'amount' => '1000.00', 'customer_id' => $customer->id]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'dues.over_limit_approved', 'subject_id' => $customer->id]);
    }

    public function test_unsent_items_block_billing_and_turned_off_methods_are_refused(): void
    {
        $order = $this->sentOrder();
        $waiter = User::factory()->role(Role::Waiter)->create();
        $this->actingAs($waiter)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create()->id, 'quantity' => 1]);

        $this->actingAs($this->cashier())->post(route('billing.settle', $order), ['payments' => [['method' => 'cash', 'amount' => '1220.00']]])
            ->assertSessionHasErrors('order');

        $order2 = $this->sentOrder();
        Settings::save(['payment_card' => false], User::factory()->superAdmin()->create());
        $this->actingAs($this->cashier())->post(route('billing.settle', $order2), ['payments' => [['method' => 'card', 'amount' => '1000.00']]])
            ->assertSessionHasErrors('payments.0.method');
    }

    public function test_bills_and_payments_cannot_be_changed(): void
    {
        $order = $this->sentOrder();
        $this->actingAs($this->cashier())->post(route('billing.settle', $order), ['payments' => [['method' => 'cash', 'amount' => '1000.00']]]);

        $bill = Bill::query()->firstOrFail();
        try {
            $bill->forceFill(['total' => '1.00'])->save();
            $this->fail('Bill total was changed.');
        } catch (LogicException) {
            $this->assertSame('1000.00', $bill->refresh()->total);
        }

        $this->expectException(LogicException::class);
        BillPayment::query()->firstOrFail()->delete();
    }
}
