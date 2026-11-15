<?php

declare(strict_types=1);

namespace Tests\Feature\Menu;

use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoadMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_loads_the_woodstone_menu_with_prices_and_photos(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $old = MenuItem::factory()->create(['code' => 'OLD-01']);
        $mine = MenuItem::factory()->create(['code' => 'MOMO-02', 'price' => '120']);
        $mine->forceFill(['image_path' => 'menu/my-own.jpg'])->save();

        $this->actingAs($admin)->post(route('menu.load-woodstone'), ['archive_others' => true])->assertSessionHasNoErrors();

        $this->assertSame(162, MenuItem::query()->where('is_active', true)->count());
        $momo = MenuItem::query()->where('code', 'MOMO-01')->firstOrFail();
        $this->assertSame('Chicken Steam Momo', $momo->name);
        $this->assertSame('170.00', $momo->price);
        $this->assertSame('menu/woodstone/momo-steam.jpg', $momo->image_path);
        Storage::disk('public')->assertExists('menu/woodstone/momo-steam.jpg');
        $this->assertSame('3900.00', MenuItem::query()->where('code', 'HD01-FULL')->value('price'));
        $this->assertSame('130.00', MenuItem::query()->where('code', 'NDL-12')->value('price'));

        // Own photo kept, price updated and audited, other items archived.
        $mine->refresh();
        $this->assertSame('menu/my-own.jpg', $mine->image_path);
        $this->assertSame('150.00', $mine->price);
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.price_changed', 'subject_id' => $mine->id]);
        $this->assertFalse($old->refresh()->is_active);

        // Running again adds nothing.
        $this->actingAs($admin)->post(route('menu.load-woodstone'))->assertSessionHasNoErrors();
        $this->assertSame(162, MenuItem::query()->where('code', '!=', 'OLD-01')->count());

        $this->artisan('woodstone:load-menu')->assertSuccessful();
    }

    public function test_only_menu_managers_can_load_it(): void
    {
        $this->post(route('menu.load-woodstone'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Waiter)->create())->post(route('menu.load-woodstone'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post(route('menu.load-woodstone'), ['archive_others' => 'maybe'])
            ->assertSessionHasErrors('archive_others');
        $this->assertSame(0, MenuItem::query()->count());
    }
}
