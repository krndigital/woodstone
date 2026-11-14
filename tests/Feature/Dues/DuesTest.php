<?php

declare(strict_types=1);

namespace Tests\Feature\Dues;

use App\Enums\LedgerType;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\DuesData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class DuesTest extends TestCase
{
    use RefreshDatabase;

    private function due(Customer $customer, string $amount, ?string $at = null): void
    {
        $entry = new LedgerEntry;
        $entry->forceFill([
            'customer_id' => $customer->id, 'type' => LedgerType::BillDue, 'amount' => $amount,
            'created_by' => User::factory()->create()->id, 'created_at' => $at ?? now(),
        ])->save();
    }

    public function test_access_by_role(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->get(route('customers.index'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Kitchen)->create())->post(route('customers.store'), ['name' => 'X'])->assertForbidden();

        $c = Customer::factory()->create();
        $this->due($c, '700.00');
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('customers/Index')->has('customers', 1)->where('customers.0.balance', '700.00')->where('totals.balance', '700.00'));
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->get(route('customers.show', $c))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('customers/Show')->has('entries', 1));
    }

    public function test_cashier_adds_customer_with_default_limit_and_cannot_change_limits(): void
    {
        $cashier = User::factory()->role(Role::Cashier)->create();

        $this->actingAs($cashier)->post(route('customers.store'), ['name' => 'Sita', 'phone' => 'abc'])->assertSessionHasErrors('phone');
        $this->actingAs($cashier)->post(route('customers.store'), ['name' => 'Sita', 'phone' => '9800-000001', 'credit_limit' => '999999'])->assertSessionHasNoErrors();

        $sita = Customer::query()->where('name', 'Sita')->firstOrFail();
        $this->assertSame('9800000001', $sita->phone);
        $this->assertSame('15000.00', $sita->credit_limit);

        $this->actingAs($cashier)->post(route('customers.store'), ['name' => 'Dup', 'phone' => '9800000001'])->assertSessionHasErrors('phone');
        $this->actingAs($cashier)->put(route('customers.update', $sita), ['name' => 'Sita K', 'phone' => '9800000001', 'credit_limit' => '50000'])
            ->assertSessionHasErrors('credit_limit');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('customers.update', $sita), ['name' => 'Sita K', 'phone' => '9800000001', 'credit_limit' => '50000', 'is_active' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame('50000.00', $sita->refresh()->credit_limit);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.updated', 'subject_id' => $sita->id]);
    }

    public function test_receive_payment_reduces_the_due(): void
    {
        $cashier = User::factory()->role(Role::Cashier)->create();
        $c = Customer::factory()->create();
        $this->due($c, '1000.00');

        $this->actingAs($cashier)->post(route('customers.payments.store', $c), ['method' => 'cash', 'amount' => '1500.00'])->assertSessionHasErrors('amount');
        $this->actingAs($cashier)->post(route('customers.payments.store', $c), ['method' => 'due', 'amount' => '100.00'])->assertSessionHasErrors('method');
        $this->actingAs($cashier)->post(route('customers.payments.store', $c), ['method' => 'qr', 'amount' => '100.00'])->assertSessionHasErrors('qr_checked');

        $this->actingAs($cashier)->post(route('customers.payments.store', $c), ['method' => 'cash', 'amount' => '600.00'])->assertSessionHasNoErrors();

        $this->assertSame(40000, $c->balancePaisa());
        $this->assertDatabaseHas('ledger_entries', ['customer_id' => $c->id, 'type' => 'payment', 'amount' => '-600.00', 'method' => 'cash']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dues.payment_received', 'subject_id' => $c->id]);
    }

    public function test_move_due_needs_manager_approval(): void
    {
        $cashier = User::factory()->role(Role::Cashier)->create();
        User::factory()->admin()->create(['username' => 'boss']);
        $from = Customer::factory()->create();
        $to = Customer::factory()->create();
        $this->due($from, '800.00');

        $this->actingAs($cashier)->post(route('customers.move', $from), ['to_customer_id' => $to->id, 'amount' => '300.00', 'note' => 'Office pays'])
            ->assertSessionHasErrors('approver_username');
        $this->actingAs($cashier)->post(route('customers.move', $from), ['to_customer_id' => $from->id, 'amount' => '300.00', 'note' => 'x'])
            ->assertSessionHasErrors('note');

        $this->actingAs($cashier)->post(route('customers.move', $from), [
            'to_customer_id' => $to->id, 'amount' => '300.00', 'note' => 'Office pays', 'approver_username' => 'boss', 'approver_secret' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame(50000, $from->balancePaisa());
        $this->assertSame(30000, $to->balancePaisa());
        $this->assertDatabaseHas('audit_logs', ['action' => 'dues.moved', 'subject_id' => $from->id]);
    }

    public function test_ageing_uses_oldest_dues_first_and_ledger_is_append_only(): void
    {
        $c = Customer::factory()->create();
        $this->due($c, '500.00', now()->subDays(75)->toDateTimeString());
        $this->due($c, '300.00', now()->subDays(40)->toDateTimeString());
        $this->due($c, '200.00', now()->subDays(5)->toDateTimeString());
        $payment = new LedgerEntry;
        $payment->forceFill(['customer_id' => $c->id, 'type' => LedgerType::Payment, 'amount' => '-600.00', 'created_by' => User::factory()->create()->id, 'created_at' => now()])->save();

        $s = DuesData::summary($c);
        $this->assertSame(40000, $s['balance']);
        $this->assertSame(0, $s['age_60_plus']);
        $this->assertSame(20000, $s['age_31_60']);
        $this->assertSame(20000, $s['age_0_30']);

        $this->expectException(LogicException::class);
        $payment->forceFill(['amount' => '0.00'])->save();
    }
}
