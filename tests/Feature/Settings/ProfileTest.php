<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk();
    }

    public function test_name_email_and_phone_can_be_updated_and_email_is_optional(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'New Name',
            'email' => '',
            'phone' => '+977 9800000000',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertNull($user->email);
        $this->assertSame('+977 9800000000', $user->phone);
    }

    public function test_role_username_and_status_cannot_be_changed_from_the_profile(): void
    {
        $user = User::factory()->role(Role::Waiter)->create(['username' => 'waiter1']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Me',
            'role' => Role::SuperAdmin->value,
            'username' => 'boss',
            'is_active' => false,
        ]);

        $user->refresh();
        $this->assertSame(Role::Waiter, $user->role);
        $this->assertSame('waiter1', $user->username);
        $this->assertTrue($user->is_active);
    }

    public function test_staff_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings/profile')->assertStatus(405);
        $this->assertModelExists($user);
    }
}
