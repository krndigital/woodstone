<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

/**
 * Sets a new password for any user from the server (forgotten owner password).
 * The password is asked with a hidden prompt, never as an argument, so it stays out of shell history.
 */
class ResetPasswordCommand extends Command
{
    protected $signature = 'woodstone:reset-password {username : The user to reset}';

    protected $description = 'Set a new password for a user (and turns the account back on)';

    public function handle(): int
    {
        $user = User::query()->where('username', mb_strtolower((string) $this->argument('username')))->first();
        if ($user === null) {
            $this->error('No user with that username. Users: '.User::query()->orderBy('username')->pluck('username')->implode(', '));

            return self::FAILURE;
        }

        $secret = password(label: 'New password', required: true);
        $confirm = password(label: 'Confirm password', required: true);
        $validator = Validator::make(
            ['password' => $secret, 'password_confirmation' => $confirm],
            ['password' => ['required', 'string', Password::default(), 'confirmed']],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->forceFill(['password' => $secret, 'is_active' => true, 'must_change_password' => false])->save();
        Audit::record('user.password_reset_cli', $user, ['username' => $user->username]);
        $this->info("Password changed for \"{$user->username}\". Sign in at ".route('login'));

        return self::SUCCESS;
    }
}
