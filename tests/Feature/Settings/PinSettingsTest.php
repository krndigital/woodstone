<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PinSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_set_their_own_pin_with_their_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user-pin.update'), [
            'current_password' => 'password',
            'pin' => '2580',
            'pin_confirmation' => '2580',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('2580', (string) $user->refresh()->pin));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.pin_set', 'user_id' => $user->id]);
    }

    public function test_wrong_password_weak_or_mismatched_pin_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user-pin.update'), [
            'current_password' => 'nope',
            'pin' => '2580',
            'pin_confirmation' => '2580',
        ])->assertSessionHasErrors('current_password');

        foreach (['1111', '1234', '9876', '12', 'abcd'] as $weak) {
            $this->actingAs($user)->put(route('user-pin.update'), [
                'current_password' => 'password',
                'pin' => $weak,
                'pin_confirmation' => $weak,
            ])->assertSessionHasErrors('pin');
        }

        $this->actingAs($user)->put(route('user-pin.update'), [
            'current_password' => 'password',
            'pin' => '2580',
            'pin_confirmation' => '2581',
        ])->assertSessionHasErrors('pin');

        $this->assertNull($user->refresh()->pin);
    }

    public function test_super_admin_and_two_factor_users_cannot_have_a_pin(): void
    {
        foreach ([User::factory()->superAdmin()->create(), User::factory()->withTwoFactor()->create()] as $user) {
            $this->actingAs($user)->put(route('user-pin.update'), [
                'current_password' => 'password',
                'pin' => '2580',
                'pin_confirmation' => '2580',
            ])->assertForbidden();
        }
    }

    public function test_staff_can_remove_their_pin(): void
    {
        $user = User::factory()->withPin()->create();

        $this->actingAs($user)->delete(route('user-pin.destroy'))->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->pin);
    }
}
