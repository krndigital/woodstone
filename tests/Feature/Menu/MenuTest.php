<?php

declare(strict_types=1);

namespace Tests\Feature\Menu;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'momo-01',
            'category_id' => MenuCategory::factory()->create()->id,
            'station_id' => Station::factory()->create()->id,
            'name' => 'Chicken Momo (Steam)',
            'description' => 'Ten pieces with tomato achar',
            'price' => '220',
            'cost' => '88.50',
            'is_veg' => false,
            'vat_applicable' => true,
            'show_on_qr' => true,
            'modifiers' => [['name' => 'Extra sauce', 'price' => '20']],
        ], $overrides);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('menu.index'))->assertRedirect(route('login'));
        $this->post(route('menu.items.store'), [])->assertRedirect(route('login'));
    }

    public function test_store_keeper_cannot_see_the_menu(): void
    {
        $this->actingAs(User::factory()->role(Role::StoreKeeper)->create())
            ->get(route('menu.index'))->assertForbidden();
    }

    public function test_waiters_see_prices_but_not_costs_or_archived_items(): void
    {
        MenuItem::factory()->create(['name' => 'Visible']);
        MenuItem::factory()->archived()->create(['name' => 'Old']);

        $this->actingAs(User::factory()->role(Role::Waiter)->create())
            ->get(route('menu.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('menu/Index')
                ->has('items', 1)
                ->where('items.0.name', 'Visible')
                ->where('items.0.price', '220.00')
                ->where('items.0.cost', null)
                ->where('items.0.margin', null)
                ->where('can.manage', false),
            );
    }

    public function test_admin_sees_cost_and_margin(): void
    {
        MenuItem::factory()->create(['price' => '200', 'cost' => '50']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('menu.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.cost', '50.00')
                ->where('items.0.margin', 75)
                ->where('can.manage', true),
            );
    }

    public function test_only_menu_managers_can_add_items(): void
    {
        foreach ([Role::Cashier, Role::Waiter, Role::Kitchen] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->post(route('menu.items.store'), $this->payload())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_admin_adds_an_item_with_modifiers(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('menu.items.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $item = MenuItem::query()->with('modifiers')->firstOrFail();
        $this->assertSame('MOMO-01', $item->code);
        $this->assertSame('220.00', $item->price);
        $this->assertSame('88.50', $item->cost);
        $this->assertCount(1, $item->modifiers);
        $this->assertSame('20.00', $item->modifiers[0]->price);
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.item_created', 'user_id' => $admin->id]);
    }

    public function test_item_input_is_validated(): void
    {
        $admin = User::factory()->admin()->create();
        MenuItem::factory()->create(['code' => 'TAKEN']);
        $off = Station::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->post(route('menu.items.store'), $this->payload([
            'code' => 'taken',
            'price' => '12.345',
            'cost' => '-5',
            'station_id' => $off->id,
            'category_id' => 999999,
            'name' => '',
            'available_from' => '25:00',
            'modifiers' => [['name' => '', 'price' => 'abc']],
        ]))->assertSessionHasErrors([
            'code', 'price', 'cost', 'station_id', 'category_id', 'name', 'available_from',
            'modifiers.0.name', 'modifiers.0.price',
        ]);
    }

    public function test_price_change_is_audited_with_old_and_new_value(): void
    {
        $admin = User::factory()->admin()->create();
        $item = MenuItem::factory()->create(['code' => 'MOMO-01', 'price' => '220']);

        $this->actingAs($admin)->put(route('menu.items.update', $item), $this->payload([
            'code' => 'MOMO-01',
            'category_id' => $item->category_id,
            'station_id' => $item->station_id,
            'price' => '240',
            'modifiers' => [],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('240.00', $item->refresh()->price);
        $log = AuditLog::query()->where('action', 'menu.price_changed')->firstOrFail();
        $this->assertSame(['old' => '220.00', 'new' => '240.00'], $log->properties);
    }

    public function test_kitchen_and_cashier_can_mark_items_sold_out_but_waiters_cannot(): void
    {
        $item = MenuItem::factory()->create();

        $this->actingAs(User::factory()->role(Role::Waiter)->create())
            ->patch(route('menu.items.availability', $item))->assertForbidden();

        $this->actingAs(User::factory()->role(Role::Kitchen)->create())
            ->patch(route('menu.items.availability', $item))->assertSessionHasNoErrors();
        $this->assertFalse($item->refresh()->is_available);

        $this->actingAs(User::factory()->role(Role::Cashier)->create())
            ->patch(route('menu.items.availability', $item));
        $this->assertTrue($item->refresh()->is_available);

        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.item_sold_out', 'subject_id' => $item->id]);
    }

    public function test_items_are_archived_and_restored_never_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $item = MenuItem::factory()->create();

        $this->actingAs($admin)->patch(route('menu.items.archive', $item))->assertSessionHasNoErrors();
        $this->assertFalse($item->refresh()->is_active);
        $this->assertModelExists($item);

        $this->actingAs($admin)->patch(route('menu.items.restore', $item));
        $this->assertTrue($item->refresh()->is_active);
    }

    public function test_photo_upload_accepts_images_only(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $item = MenuItem::factory()->create();

        $this->actingAs($admin)->post(route('menu.items.image', $item), [
            'image' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');

        $this->actingAs($admin)->post(route('menu.items.image', $item), [
            'image' => UploadedFile::fake()->image('momo.jpg', 600, 400),
        ])->assertSessionHasNoErrors();

        $path = (string) $item->refresh()->image_path;
        $this->assertStringStartsWith('menu/', $path);
        $this->assertStringNotContainsString('momo.jpg', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)->delete(route('menu.items.image.destroy', $item));
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($item->refresh()->image_path);
    }

    public function test_categories_one_level_deep_and_unique(): void
    {
        $admin = User::factory()->admin()->create();
        $momo = MenuCategory::factory()->create(['name' => 'Momo']);
        $sub = MenuCategory::factory()->create(['name' => 'Steam', 'parent_id' => $momo->id]);

        $this->actingAs($admin)->post(route('menu.categories.store'), ['name' => 'momo'])
            ->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('menu.categories.store'), ['name' => 'Deep', 'parent_id' => $sub->id])
            ->assertSessionHasErrors('parent_id');
        $this->actingAs($admin)->post(route('menu.categories.store'), ['name' => 'Fried', 'parent_id' => $momo->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('menu_categories', ['name' => 'Fried', 'parent_id' => $momo->id]);
    }

    public function test_a_category_with_items_cannot_be_deleted_or_turned_off(): void
    {
        $admin = User::factory()->admin()->create();
        $item = MenuItem::factory()->create();
        $category = $item->category;
        $this->assertNotNull($category);

        $this->actingAs($admin)->delete(route('menu.categories.destroy', $category))->assertSessionHasErrors('category');
        $this->actingAs($admin)->put(route('menu.categories.update', $category), ['name' => $category->name, 'is_active' => false])
            ->assertSessionHasErrors('is_active');

        $empty = MenuCategory::factory()->create();
        $this->actingAs($admin)->delete(route('menu.categories.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertModelMissing($empty);
    }

    public function test_stations_are_managed_and_cannot_be_turned_off_while_in_use(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('menu.stations.store'), ['name' => 'Momo corner', 'kind' => 'kitchen'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('menu.stations.store'), ['name' => 'Momo corner', 'kind' => 'oven'])
            ->assertSessionHasErrors(['name', 'kind']);

        $item = MenuItem::factory()->create();
        $station = $item->station;
        $this->assertNotNull($station);

        $this->actingAs($admin)->put(route('menu.stations.update', $station), ['name' => $station->name, 'kind' => 'kitchen', 'is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->actingAs(User::factory()->role(Role::Cashier)->create())
            ->post(route('menu.stations.store'), ['name' => 'X', 'kind' => 'bar'])
            ->assertForbidden();
    }
}
