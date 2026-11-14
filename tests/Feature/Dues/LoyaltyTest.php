<?php

declare(strict_types=1);

namespace Tests\Feature\Dues;

use App\Enums\Role;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\Loyalty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $price): Order
    {
        $cashier = User::factory()->role(Role::Cashier)->create();
        $this->actingAs($cashier)->post(route('pos.orders.store'), ['type' => 'counter']);
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($cashier)->post(route('pos.orders.items.store', $order), ['menu_item_id' => MenuItem::factory()->create(['price' => $price, 'name' => 'Momo'])->id, 'quantity' => 1]);
        $this->actingAs($cashier)->post(route('pos.orders.kot', $order));

        return $order;
    }

    public function test_earn_points_then_pay_with_points(): void
    {
        $customer = Customer::factory()->create(['name' => 'Gita', 'birthday' => now()->addDays(2)->subYears(30)->toDateString()]);
        $cashier = User::factory()->role(Role::Cashier)->create();

        $this->actingAs($cashier)->post(route('billing.settle', $this->order('1250.00')), ['customer_id' => $customer->id, 'payments' => [['method' => 'cash', 'amount' => '1250.00']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(12, Loyalty::balance($customer));
        $this->assertSame($customer->id, Bill::query()->firstOrFail()->customer_id);

        // 10 points pay Rs 10; more points than she has is refused; points need a customer.
        $order = $this->order('100.00');
        $this->actingAs($cashier)->post(route('billing.settle', $order), ['payments' => [['method' => 'points', 'amount' => '10.00'], ['method' => 'cash', 'amount' => '90.00']]])
            ->assertSessionHasErrors('customer_id');
        $this->actingAs($cashier)->post(route('billing.settle', $order), ['customer_id' => $customer->id, 'payments' => [['method' => 'points', 'amount' => '50.00'], ['method' => 'cash', 'amount' => '50.00']]])
            ->assertSessionHasErrors('payments');
        $this->actingAs($cashier)->post(route('billing.settle', $order), ['customer_id' => $customer->id, 'payments' => [['method' => 'points', 'amount' => '10.00'], ['method' => 'cash', 'amount' => '90.00']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Loyalty::balance($customer)); // 12 − 10 + 0 (Rs 90 earns nothing)

        $this->actingAs($cashier)->get(route('customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page->where('history.visits', 2)->where('history.favourites.0.name', 'Momo')->where('points', 2));
        $this->actingAs($cashier)->get(route('customers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('birthdays.0.name', 'Gita')->where('customers.0.points', 2));

        $this->expectException(LogicException::class);
        LoyaltyEntry::query()->firstOrFail()->delete();
    }

    public function test_points_expire_and_tiers_follow_spend(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $e = new LoyaltyEntry;
        $e->forceFill(['customer_id' => $customer->id, 'points' => 30, 'type' => 'earn', 'expires_on' => now()->subDay()->toDateString(), 'created_by' => $user->id, 'created_at' => now()->subYear()])->save();

        $this->assertSame(0, Loyalty::balance($customer, $user));
        $this->assertDatabaseHas('loyalty_entries', ['customer_id' => $customer->id, 'type' => 'expire', 'points' => -30]);
        $this->assertSame('Regular', Loyalty::tier(100));
        $this->assertSame('Silver', Loyalty::tier(2500000));
        $this->assertSame('Gold', Loyalty::tier(7500000));
    }

    public function test_cancelled_bill_takes_back_points_and_profile_fields_save(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->role(Role::Cashier)->create())->post(route('billing.settle', $this->order('500.00')), ['customer_id' => $customer->id, 'payments' => [['method' => 'cash', 'amount' => '500.00']]]);
        $this->assertSame(5, Loyalty::balance($customer));
        $this->actingAs(User::factory()->admin()->create())->post(route('billing.bills.return', Bill::query()->firstOrFail()), ['full_cancel' => true, 'refund_method' => 'cash', 'reason' => 'Wrong'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Loyalty::balance($customer));

        $this->actingAs(User::factory()->role(Role::Cashier)->create())->put(route('customers.update', $customer), ['name' => $customer->name, 'phone' => $customer->phone, 'email' => 'Gita@Example.com', 'birthday' => '1990-05-01', 'preferred_seat' => 'Cabin 2'])
            ->assertSessionHasNoErrors();
        $this->assertSame('gita@example.com', $customer->refresh()->email);
        $this->assertSame('Cabin 2', $customer->preferred_seat);
    }
}
