<?php

declare(strict_types=1);

namespace Tests\Feature\Floor;

use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FloorTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_by_role(): void
    {
        $this->get(route('floor.index'))->assertRedirect(route('login'));

        foreach ([Role::Kitchen, Role::StoreKeeper] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('floor.index'))->assertForbidden();
        }

        DiningTable::factory()->create(['code' => 'G1']);
        $this->actingAs(User::factory()->role(Role::Waiter)->create())
            ->get(route('floor.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('floor/Index')->has('tables', 1)->where('can.manage', false));
    }

    public function test_reserve_and_cancel_a_free_table(): void
    {
        $table = DiningTable::factory()->create();
        $waiter = User::factory()->role(Role::Waiter)->create();

        $this->actingAs($waiter)->patch(route('floor.tables.status', $table), ['status' => 'reserved'])
            ->assertSessionHasErrors(['reserved_for', 'reserved_at']);

        $this->actingAs($waiter)->patch(route('floor.tables.status', $table), [
            'status' => 'reserved', 'reserved_for' => 'Mr. Sharma', 'reserved_at' => '2026-11-14 19:30',
        ])->assertSessionHasNoErrors();

        $table->refresh();
        $this->assertSame(TableStatus::Reserved, $table->status);
        $this->assertSame('Mr. Sharma', $table->reserved_for);

        $this->actingAs($waiter)->patch(route('floor.tables.status', $table), ['status' => 'free'])->assertSessionHasNoErrors();
        $table->refresh();
        $this->assertSame(TableStatus::Free, $table->status);
        $this->assertNull($table->reserved_for);
        $this->assertDatabaseHas('audit_logs', ['action' => 'floor.status_changed', 'subject_id' => $table->id]);
    }

    public function test_occupied_tables_cannot_be_changed_by_hand_but_cleaning_can_be_cleared(): void
    {
        $cashier = User::factory()->role(Role::Cashier)->create();
        $busy = DiningTable::factory()->status(TableStatus::Occupied)->create();
        $dirty = DiningTable::factory()->status(TableStatus::Cleaning)->create();

        $this->actingAs($cashier)->patch(route('floor.tables.status', $busy), ['status' => 'free'])->assertSessionHasErrors('status');
        $this->assertSame(TableStatus::Occupied, $busy->refresh()->status);

        $this->actingAs($cashier)->patch(route('floor.tables.status', $dirty), ['status' => 'free'])->assertSessionHasNoErrors();
        $this->assertSame(TableStatus::Free, $dirty->refresh()->status);

        $this->actingAs($cashier)->patch(route('floor.tables.status', $dirty), ['status' => 'occupied'])->assertSessionHasErrors('status');
    }

    public function test_only_floor_managers_set_up_zones_and_tables(): void
    {
        $this->actingAs(User::factory()->role(Role::Cashier)->create())
            ->post(route('floor.zones.store'), ['name' => 'Roof'])->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('floor.zones.store'), ['name' => 'Roof'])->assertSessionHasNoErrors();
        $zone = Zone::query()->where('name', 'Roof')->firstOrFail();

        $this->actingAs($admin)->post(route('floor.tables.store'), ['zone_id' => $zone->id, 'code' => 'r1', 'kind' => 'table', 'seats' => 4])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dining_tables', ['code' => 'R1', 'status' => 'free']);

        $this->actingAs($admin)->post(route('floor.tables.store'), ['zone_id' => $zone->id, 'code' => 'R1', 'kind' => 'boat', 'seats' => 0])
            ->assertSessionHasErrors(['code', 'kind', 'seats']);
    }

    public function test_a_table_with_guests_cannot_be_turned_off(): void
    {
        $admin = User::factory()->admin()->create();
        $table = DiningTable::factory()->status(TableStatus::Occupied)->create();

        $this->actingAs($admin)->put(route('floor.tables.update', $table), [
            'zone_id' => $table->zone_id, 'code' => $table->code, 'kind' => 'table', 'seats' => 4, 'is_active' => false,
        ])->assertSessionHasErrors('is_active');
    }
}
