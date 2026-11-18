<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RolesAndBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_changes_what_a_role_may_do(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $waiter = User::factory()->role(Role::Waiter)->create();
        $this->actingAs($waiter)->get(route('menu.index'))->assertOk();
        $this->assertFalse($waiter->hasPermission(Permission::ManageMenu));

        $this->actingAs($owner)->get(route('admin.roles.edit'))->assertOk();
        $this->actingAs($owner)->put(route('admin.roles.update'), ['roles' => [
            'waiter' => ['pos.use', 'floor.view', 'menu.view', 'menu.manage'],
            'cashier' => [],
        ]])->assertSessionHasNoErrors();
        Settings::flush();

        $this->assertTrue($waiter->fresh()?->hasPermission(Permission::ManageMenu));
        $this->assertFalse(User::factory()->role(Role::Cashier)->create()->hasPermission(Permission::UsePos));
        // Roles not sent keep the default set; Super Admin always has everything.
        $this->assertTrue(User::factory()->role(Role::Kitchen)->create()->hasPermission(Permission::ViewKitchen));
        $this->assertTrue($owner->hasPermission(Permission::ManageSettings));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_roles_page_rules(): void
    {
        $this->get(route('admin.roles.edit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Admin)->create())->get(route('admin.roles.edit'))->assertForbidden();
        $owner = User::factory()->superAdmin()->create();
        $this->actingAs($owner)->put(route('admin.roles.update'), ['roles' => ['waiter' => ['settings.manage']]])->assertSessionHasErrors('roles.waiter.0');
        $this->actingAs($owner)->put(route('admin.roles.update'), ['roles' => ['super_admin' => []]])->assertSessionHasErrors('roles');
    }

    public function test_logo_and_favicon_upload(): void
    {
        Storage::fake('public');
        $owner = User::factory()->superAdmin()->create();

        $this->post(route('admin.settings.branding'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role(Role::Cashier)->create())
            ->post(route('admin.settings.branding'), ['kind' => 'logo', 'file' => UploadedFile::fake()->image('l.png')])->assertForbidden();
        $this->actingAs($owner)->post(route('admin.settings.branding'), ['kind' => 'logo', 'file' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors('file');

        $this->actingAs($owner)->post(route('admin.settings.branding'), ['kind' => 'logo', 'file' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertSessionHasNoErrors();
        Settings::flush();
        $path = Settings::string('logo_path');
        Storage::disk('public')->assertExists($path);
        $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('branding.logo', Storage::disk('public')->url($path)));

        $this->actingAs($owner)->post(route('admin.settings.branding'), ['kind' => 'favicon', 'file' => UploadedFile::fake()->image('fav.png', 64, 64)])->assertSessionHasNoErrors();
        Settings::flush();
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee(Storage::disk('public')->url(Settings::string('favicon_path')), false);

        $this->actingAs($owner)->delete(route('admin.settings.branding.remove', 'logo'))->assertSessionHasNoErrors();
        Settings::flush();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull(Settings::get('logo_path'));
    }
}
