<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the first Super Admin (owner). Asks for the password with a hidden prompt, so it never
 * appears in shell history or logs. Refuses to run once a Super Admin exists — add more from the app.
 */
class CreateOwnerCommand extends Command
{
    protected $signature = 'woodstone:create-owner';

    protected $description = 'Create the first Super Admin (owner) account';

    public function handle(): int
    {
        if (User::query()->where('role', Role::SuperAdmin->value)->exists()) {
            $this->error('A Super Admin already exists. Add more users from the app (Users page).');

            return self::FAILURE;
        }

        $name = text(label: 'Full name', required: true);
        $username = mb_strtolower(text(label: 'Username (letters, numbers, dot, dash)', required: true));
        $email = text(label: 'Email (optional)');
        $secret = password(label: 'Password', required: true);
        $confirm = password(label: 'Confirm password', required: true);

        $validator = Validator::make(
            ['name' => $name, 'username' => $username, 'email' => $email === '' ? null : $email, 'password' => $secret, 'password_confirmation' => $confirm],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', Password::default(), 'confirmed'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'username' => $username, 'email' => $email === '' ? null : mb_strtolower($email)]);
        $user->forceFill([
            'password' => $secret,
            'role' => Role::SuperAdmin,
            'is_active' => true,
            'must_change_password' => false,
        ])->save();

        Audit::record('user.owner_created', $user, ['username' => $username]);

        $this->info("Super Admin \"{$username}\" created. Sign in at ".route('login'));

        return self::SUCCESS;
    }
}
