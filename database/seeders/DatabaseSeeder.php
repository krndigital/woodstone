<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for local development only. Production uses `php artisan woodstone:create-owner`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command->warn('Demo users are only seeded in local/testing. Use: php artisan woodstone:create-owner');

            return;
        }

        $demo = [
            ['Owner', 'owner', Role::SuperAdmin, null],
            ['Manager', 'manager', Role::Admin, null],
            ['Ram (Cashier)', 'cashier', Role::Cashier, '2580'],
            ['Sunita (Waiter)', 'waiter', Role::Waiter, '3691'],
            ['Maya (Kitchen)', 'kitchen', Role::Kitchen, '4702'],
            ['Suraj (Bar)', 'bar', Role::Bartender, '5813'],
            ['Bimal (Store)', 'store', Role::StoreKeeper, '6924'],
        ];

        foreach ($demo as [$name, $username, $role, $pin]) {
            User::factory()->role($role)->state(array_filter([
                'name' => $name,
                'username' => $username,
                'email' => $username.'@woodstone.test',
            ]))->when($pin !== null, fn ($f) => $f->withPin($pin))->create();
        }

        $this->command->info('Demo users created. Password for all: "password". PINs: cashier 2580, waiter 3691, kitchen 4702, bar 5813, store 6924.');
    }
}
