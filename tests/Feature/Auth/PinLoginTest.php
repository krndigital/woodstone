<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Terminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = Str::random(64);
        $this->terminal = new Terminal(['name' => 'Counter']);
        $this->terminal->forceFill(['token_hash' => Terminal::hashToken($this->token)])->save();
    }

    private function onTerminal(): static
    {
        return $this->withCookie(Terminal::COOKIE, $this->token);
    }

    public function test_pin_screen_needs_a_registered_terminal(): void
    {
        $this->get(route('pin.create'))->assertRedirect(route('login'));
    }

    public function test_pin_screen_lists_only_eligible_staff(): void
    {
        $cashier = User::factory()->withPin()->create(['name' => 'A Cashier']);
        User::factory()->superAdmin()->withPin()->create(['name' => 'Owner']);
        User::factory()->create(['name' => 'No Pin']);
        User::factory()->inactive()->withPin()->create(['name' => 'Off']);
        User::factory()->withPin()->create(['name' => 'Locked', 'pin_locked_at' => now()]);
        User::factory()->withPin()->withTwoFactor()->create(['name' => 'Has 2FA']);

        $this->onTerminal()->get(route('pin.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Pin')
                ->where('terminal', 'Counter')
                ->has('users', 1)
                ->where('users.0.id', $cashier->id)
                ->missing('users.0.pin'),
            );
    }

    public function test_correct_pin_signs_in_and_is_audited(): void
    {
        $user = User::factory()->withPin('2580')->create();

        $this->onTerminal()
            ->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '2580'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'user_id' => $user->id,
            'terminal_id' => $this->terminal->id,
        ]);
    }

    public function test_pin_does_not_work_without_a_terminal(): void
    {
        $user = User::factory()->withPin('2580')->create();

        $this->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '2580'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_pin_does_not_work_on_a_revoked_terminal(): void
    {
        $user = User::factory()->withPin('2580')->create();
        $this->terminal->forceFill(['revoked_at' => now()])->save();

        $this->onTerminal()
            ->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '2580'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_three_wrong_pins_lock_the_pin(): void
    {
        $user = User::factory()->withPin('2580')->create();

        foreach (['1111', '2222'] as $wrong) {
            $this->onTerminal()->post(route('pin.store'), ['user_id' => $user->id, 'pin' => $wrong])
                ->assertSessionHasErrors('pin');
        }
        $this->assertNull($user->refresh()->pin_locked_at);

        $this->onTerminal()->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '3333'])
            ->assertSessionHasErrors(['pin' => 'PIN is locked after 3 wrong tries. Please sign in with your password.']);

        $this->assertNotNull($user->refresh()->pin_locked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.pin_locked', 'subject_id' => $user->id]);

        // Even the right PIN is refused now.
        $this->onTerminal()->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '2580'])
            ->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_super_admin_can_never_use_a_pin(): void
    {
        $owner = User::factory()->superAdmin()->withPin('2580')->create();

        $this->onTerminal()->post(route('pin.store'), ['user_id' => $owner->id, 'pin' => '2580'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_turned_off_user_cannot_use_a_pin(): void
    {
        $user = User::factory()->inactive()->withPin('2580')->create();

        $this->onTerminal()->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '2580'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_pin_is_validated(): void
    {
        $user = User::factory()->withPin('2580')->create();

        $this->onTerminal()->post(route('pin.store'), ['user_id' => $user->id, 'pin' => '12a4'])
            ->assertSessionHasErrors('pin');
        $this->onTerminal()->post(route('pin.store'), ['pin' => '2580'])
            ->assertSessionHasErrors('user_id');
    }

    public function test_signed_in_users_cannot_open_the_pin_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('pin.create'))
            ->assertRedirect();
    }

    public function test_logout_on_a_terminal_returns_to_the_pin_screen(): void
    {
        $user = User::factory()->role(Role::Waiter)->create();

        $this->actingAs($user)->onTerminal()
            ->post(route('logout'))
            ->assertRedirect(route('pin.create'));
    }
}
