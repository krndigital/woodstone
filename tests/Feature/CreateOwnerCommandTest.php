<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_super_admin(): void
    {
        $this->artisan('woodstone:create-owner')
            ->expectsQuestion('Full name', 'Bhagwan')
            ->expectsQuestion('Username (letters, numbers, dot, dash)', 'Owner')
            ->expectsQuestion('Email (optional)', '')
            ->expectsQuestion('Password', 'owner-secret-1')
            ->expectsQuestion('Confirm password', 'owner-secret-1')
            ->assertSuccessful();

        $owner = User::query()->where('username', 'owner')->firstOrFail();
        $this->assertSame(Role::SuperAdmin, $owner->role);
        $this->assertTrue(Hash::check('owner-secret-1', $owner->password));
    }

    public function test_it_refuses_when_a_super_admin_exists(): void
    {
        User::factory()->superAdmin()->create();

        $this->artisan('woodstone:create-owner')->assertFailed();
    }

    public function test_it_rejects_mismatched_passwords(): void
    {
        $this->artisan('woodstone:create-owner')
            ->expectsQuestion('Full name', 'Bhagwan')
            ->expectsQuestion('Username (letters, numbers, dot, dash)', 'owner')
            ->expectsQuestion('Email (optional)', '')
            ->expectsQuestion('Password', 'owner-secret-1')
            ->expectsQuestion('Confirm password', 'different-1')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
