<?php

declare(strict_types=1);

namespace Tests\Feature\Guest;

use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\ServiceCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_page_shows_live_menu_without_login(): void
    {
        $table = DiningTable::factory()->create(['code' => 'C2']);
        MenuItem::factory()->create(['name' => 'Momo', 'show_on_qr' => true]);
        MenuItem::factory()->create(['name' => 'Secret', 'show_on_qr' => false]);
        MenuItem::factory()->soldOut()->create(['name' => 'Sold', 'show_on_qr' => true]);

        $this->get(route('guest.table', $table->qr_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('guest/Table')->where('table.label', 'Table C2')->where('table.can_call', false)->has('items', 2)->missing('items.0.cost'));

        $this->get('/t/not-a-token')->assertNotFound();
        $this->get('/t/'.str_repeat('a', 32))->assertNotFound();
    }

    public function test_call_only_from_open_table_and_once_a_minute(): void
    {
        $table = DiningTable::factory()->create();
        $this->post(route('guest.calls.store', $table->qr_token), ['type' => 'waiter'])->assertSessionHasErrors('type');

        $table->setStatus(TableStatus::Occupied);
        $this->post(route('guest.calls.store', $table->qr_token), ['type' => 'dance'])->assertSessionHasErrors('type');
        $this->post(route('guest.calls.store', $table->qr_token), ['type' => 'water', 'note' => '2 bottles'])->assertSessionHasNoErrors();
        $this->post(route('guest.calls.store', $table->qr_token), ['type' => 'bill'])->assertSessionHasErrors('type');

        $call = ServiceCall::query()->firstOrFail();
        $this->assertSame('water', $call->type);
        $this->getJson(route('guest.calls.status', [$table->qr_token, $call->public_id]))->assertOk()->assertJson(['status' => 'sent']);
        // Another seat's token cannot read this call.
        $other = DiningTable::factory()->create();
        $this->getJson(route('guest.calls.status', [$other->qr_token, $call->public_id]))->assertNotFound();
    }

    public function test_staff_accept_and_done_and_bill_request_marks_table(): void
    {
        $table = DiningTable::factory()->status(TableStatus::Occupied)->create(['code' => 'G4']);
        $this->post(route('guest.calls.store', $table->qr_token), ['type' => 'bill']);
        $call = ServiceCall::query()->firstOrFail();
        $waiter = User::factory()->role(Role::Waiter)->create(['name' => 'Sunita Rai']);

        $this->getJson(route('calls.pending'))->assertUnauthorized();
        $this->actingAs(User::factory()->role(Role::Kitchen)->create())->getJson(route('calls.pending'))->assertForbidden();
        $this->actingAs($waiter)->getJson(route('calls.pending'))->assertOk()->assertJsonPath('calls.0.table', 'Table G4');

        $this->actingAs($waiter)->patchJson(route('calls.accept', $call))->assertOk();
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->patchJson(route('calls.accept', $call))->assertStatus(409);
        $this->app['auth']->forgetGuards();
        $this->getJson(route('guest.calls.status', [$table->qr_token, $call->public_id]))->assertJson(['status' => 'accepted', 'waiter' => 'Sunita']);

        $this->actingAs($waiter)->patchJson(route('calls.done', $call))->assertOk();
        $this->assertSame('done', $call->refresh()->status);
        $this->assertSame(TableStatus::BillAsked, $table->refresh()->status);
    }

    public function test_kitchen_can_ping_waiters_and_qr_can_be_regenerated(): void
    {
        $table = DiningTable::factory()->create();
        $this->actingAs(User::factory()->role(Role::Kitchen)->create())->postJson(route('calls.ping'), ['dining_table_id' => $table->id])->assertOk();
        $this->actingAs(User::factory()->role(Role::StoreKeeper)->create())->postJson(route('calls.ping'), ['dining_table_id' => $table->id])->assertForbidden();
        $this->assertDatabaseHas('service_calls', ['type' => 'ping', 'dining_table_id' => $table->id]);

        $old = $table->qr_token;
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->post(route('floor.tables.qr', $table))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post(route('floor.tables.qr', $table))->assertSessionHasNoErrors();
        $this->assertNotSame($old, $table->refresh()->qr_token);
        $this->get(route('guest.table', (string) $old))->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get(route('floor.qr-cards'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('tables', 1));
    }

    public function test_pwa_manifest_is_served(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertJson((string) file_get_contents(public_path('manifest.webmanifest')));
    }
}
