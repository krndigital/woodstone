<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Actions\Users\GuardLastSuperAdmin;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sita KC',
            'username' => 'sita',
            'email' => '',
            'phone' => '9800000000',
            'role' => Role::Waiter->value,
            'password' => 'temporary-pass-1',
            'pin' => '3691',
        ], $overrides);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_staff_without_permission_get_403(): void
    {
        foreach ([Role::Cashier, Role::Waiter, Role::Kitchen, Role::Bartender, Role::StoreKeeper] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('users.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_sees_the_user_list_without_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->withPin()->create(['name' => 'Ram']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/Index')
                ->has('users', 2)
                ->missing('users.0.password')
                ->missing('users.0.pin')
                ->where('assignableRoles', fn ($roles) => collect($roles)->pluck('value')->doesntContain(Role::SuperAdmin->value)),
            );
    }

    public function test_admin_creates_a_staff_user_who_must_change_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.store'), $this->payload(['username' => 'Sita.KC']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('username', 'sita.kc')->firstOrFail();
        $this->assertSame(Role::Waiter, $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('temporary-pass-1', $user->password));
        $this->assertTrue(Hash::check('3691', (string) $user->pin));
        $this->assertNull($user->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'user_id' => $admin->id, 'subject_id' => $user->id]);
    }

    public function test_new_user_is_forced_to_the_password_page_until_they_change_it(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('security.edit'));

        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'my-own-pass-9',
            'password_confirmation' => 'my-own-pass-9',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->refresh()->must_change_password);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_admin_cannot_create_or_touch_a_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('users.store'), $this->payload(['role' => Role::SuperAdmin->value, 'pin' => '']))
            ->assertForbidden();
        $this->actingAs($admin)->put(route('users.update', $owner), $this->payload(['username' => $owner->username, 'role' => Role::SuperAdmin->value, 'pin' => '']))
            ->assertForbidden();
        $this->actingAs($admin)->patch(route('users.status', $owner))->assertForbidden();
        $this->actingAs($admin)->put(route('users.password', $owner), ['password' => 'another-pass-1'])->assertForbidden();

        $this->assertTrue($owner->refresh()->is_active);
    }

    public function test_admin_cannot_promote_someone_to_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $cashier = User::factory()->create();

        $this->actingAs($admin)->put(route('users.update', $cashier), $this->payload([
            'username' => $cashier->username, 'role' => Role::SuperAdmin->value, 'pin' => '',
        ]))->assertForbidden();

        $this->assertSame(Role::Cashier, $cashier->refresh()->role);
    }

    public function test_super_admin_can_create_another_super_admin_without_a_pin(): void
    {
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)->post(route('users.store'), $this->payload(['username' => 'partner', 'role' => Role::SuperAdmin->value, 'pin' => '']))
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('users.store'), $this->payload(['username' => 'partner2', 'role' => Role::SuperAdmin->value, 'pin' => '2580']))
            ->assertSessionHasErrors('pin');

        $this->assertNull(User::query()->where('username', 'partner')->value('pin'));
    }

    public function test_input_is_validated(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['username' => 'taken']);

        $this->actingAs($admin)->post(route('users.store'), $this->payload([
            'username' => 'Bad Name!',
            'role' => 'boss',
            'password' => 'short',
            'pin' => '1234',
            'phone' => 'call me',
        ]))->assertSessionHasErrors(['username', 'role', 'password', 'pin', 'phone']);

        $this->actingAs($admin)->post(route('users.store'), $this->payload(['username' => 'taken']))
            ->assertSessionHasErrors('username');
    }

    public function test_nobody_changes_their_own_role_or_turns_themselves_off(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('users.update', $admin), $this->payload([
            'username' => $admin->username, 'role' => Role::Cashier->value, 'pin' => '',
        ]))->assertSessionHasErrors('role');

        $this->actingAs($admin)->patch(route('users.status', $admin))->assertSessionHasErrors('status');
        $this->assertTrue($admin->refresh()->is_active);
        $this->assertSame(Role::Admin, $admin->role);
    }

    public function test_the_last_active_super_admin_is_always_kept(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $second = User::factory()->superAdmin()->create();

        // With two, one may be turned off.
        $this->actingAs($owner)->patch(route('users.status', $second))->assertSessionHasNoErrors();
        $this->assertFalse($second->refresh()->is_active);

        // Now the owner is the last one: the guard refuses to demote or turn them off.
        $this->expectException(ValidationException::class);
        GuardLastSuperAdmin::check($owner->refresh());
    }

    public function test_deactivating_signs_the_user_out(): void
    {
        $admin = User::factory()->admin()->create();
        $waiter = User::factory()->role(Role::Waiter)->create();

        $this->actingAs($admin)->patch(route('users.status', $waiter))->assertSessionHasNoErrors();

        $this->assertFalse($waiter->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'subject_id' => $waiter->id]);

        $this->actingAs($waiter)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_resets_a_password_and_clears_or_unlocks_a_pin(): void
    {
        $admin = User::factory()->admin()->create();
        $cashier = User::factory()->withPin()->create(['pin_failed_attempts' => 3, 'pin_locked_at' => now()]);

        $this->actingAs($admin)->put(route('users.password', $cashier), ['password' => 'fresh-pass-12'])
            ->assertSessionHasNoErrors();
        $cashier->refresh();
        $this->assertTrue(Hash::check('fresh-pass-12', $cashier->password));
        $this->assertTrue($cashier->must_change_password);

        $this->actingAs($admin)->patch(route('users.pin.unlock', $cashier))->assertSessionHasNoErrors();
        $this->assertNull($cashier->refresh()->pin_locked_at);

        $this->actingAs($admin)->delete(route('users.pin.clear', $cashier))->assertSessionHasNoErrors();
        $this->assertNull($cashier->refresh()->pin);

        foreach (['user.password_reset', 'user.pin_unlocked', 'user.pin_cleared'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'subject_id' => $cashier->id]);
        }
    }

    public function test_changing_a_role_to_super_admin_removes_the_pin(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $manager = User::factory()->admin()->withPin()->create();

        $this->actingAs($owner)->put(route('users.update', $manager), $this->payload([
            'username' => $manager->username, 'role' => Role::SuperAdmin->value, 'pin' => '',
        ]))->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertSame(Role::SuperAdmin, $manager->role);
        $this->assertNull($manager->pin);
    }
}
