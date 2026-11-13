<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_home_sends_guests_to_login_and_users_to_dashboard(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_users_sign_in_with_username_and_password(): void
    {
        $user = User::factory()->create(['username' => 'ram.cashier']);

        $response = $this->post(route('login.store'), [
            'username' => 'ram.cashier',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_username_is_not_case_sensitive(): void
    {
        $user = User::factory()->create(['username' => 'sunita']);

        $this->post(route('login.store'), ['username' => '  SUNITA ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_cannot_be_used_instead_of_username(): void
    {
        $user = User::factory()->create(['username' => 'maya', 'email' => 'maya@example.com']);

        $this->post(route('login.store'), ['username' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.failed', 'subject_id' => $user->id]);
    }

    public function test_unknown_username_is_rejected_with_the_same_message(): void
    {
        $this->post(route('login.store'), [
            'username' => 'nobody',
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::query()->where('action', 'auth.failed')->count());
    }

    public function test_turned_off_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ])->assertSessionHasErrors(['username' => 'This account is turned off. Please ask your manager.']);

        $this->assertGuest();
    }

    public function test_a_turned_off_user_is_signed_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_sign_in_unlocks_a_locked_pin(): void
    {
        $user = User::factory()->withPin()->create(['pin_failed_attempts' => 3, 'pin_locked_at' => now()]);

        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password']);

        $user->refresh();
        $this->assertNull($user->pin_locked_at);
        $this->assertSame(0, $user->pin_failed_attempts);
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_logout_and_go_back_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_sign_in_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong']);
        }

        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
            ->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_public_registration_and_email_reset_are_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }
}
