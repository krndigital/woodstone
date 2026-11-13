<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a staff account. Role, password and PIN are set explicitly here,
 * never through mass assignment.
 */
final class SaveUser
{
    /**
     * @param  array{name: string, username: string, email?: string|null, phone?: string|null, role: string, password?: string|null, pin?: string|null}  $data
     */
    public function handle(array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $creating = $user === null;
            $user ??= new User;
            $role = Role::from($data['role']);

            if (! $creating && $user->role !== $role) {
                GuardLastSuperAdmin::check($user);
            }

            $before = $creating ? [] : ['role' => $user->role->value, 'username' => $user->username, 'name' => $user->name];

            $user->fill([
                'name' => $data['name'],
                'username' => mb_strtolower($data['username']),
                'email' => isset($data['email']) && $data['email'] !== '' ? mb_strtolower($data['email']) : null,
                'phone' => $data['phone'] ?? null,
            ]);
            $user->forceFill(['role' => $role]);

            if ($creating) {
                $user->forceFill([
                    'password' => (string) $data['password'],
                    'must_change_password' => true,
                    'is_active' => true,
                ]);
            }

            if ($role->canUsePin() === false) {
                $user->forceFill(['pin' => null, 'pin_set_at' => null]);
            } elseif (! empty($data['pin'])) {
                $user->forceFill(['pin' => $data['pin'], 'pin_set_at' => now(), 'pin_failed_attempts' => 0, 'pin_locked_at' => null]);
            }

            $user->save();

            Audit::record($creating ? 'user.created' : 'user.updated', $user, $creating
                ? ['role' => $role->value, 'username' => $user->username]
                : ['before' => $before, 'after' => ['role' => $role->value, 'username' => $user->username, 'name' => $user->name]]);

            return $user;
        });
    }
}
