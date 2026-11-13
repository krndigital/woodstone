<?php

declare(strict_types=1);

namespace Tests\Feature\Terminals;

use App\Enums\Role;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_manage_terminals(): void
    {
        $this->get(route('terminals.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->role(Role::Cashier)->create())
            ->get(route('terminals.index'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('terminals.index'))->assertOk();
    }

    public function test_registering_stores_only_a_hash_and_sets_a_cookie(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('terminals.store'), ['name' => 'Counter PC'])
            ->assertSessionHasNoErrors()
            ->assertCookie(Terminal::COOKIE);

        $terminal = Terminal::query()->firstOrFail();
        $token = $response->getCookie(Terminal::COOKIE)?->getValue();

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertSame(Terminal::hashToken($token), $terminal->token_hash);
        $this->assertNotSame($token, $terminal->token_hash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'terminal.registered', 'subject_id' => $terminal->id]);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('terminals.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_revoking_stops_pin_sign_in(): void
    {
        $admin = User::factory()->admin()->create();
        $terminal = new Terminal(['name' => 'Garden tablet']);
        $terminal->forceFill(['token_hash' => Terminal::hashToken(str_repeat('a', 64))])->save();

        $this->actingAs($admin)->delete(route('terminals.destroy', $terminal))->assertSessionHasNoErrors();

        $this->assertNotNull($terminal->refresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'terminal.revoked', 'subject_id' => $terminal->id]);
    }
}
