<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_a_new_password_with_a_hidden_prompt(): void
    {
        $user = User::factory()->superAdmin()->create(['username' => 'owner']);
        $user->forceFill(['is_active' => false])->save();

        $this->artisan('woodstone:reset-password', ['username' => 'Owner'])
            ->expectsQuestion('New password', 'new-secret-12')
            ->expectsQuestion('Confirm password', 'new-secret-12')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-12', $user->password));
        $this->assertTrue($user->is_active);
        $this->artisan('woodstone:reset-password', ['username' => 'nobody'])->assertFailed();
    }
}
