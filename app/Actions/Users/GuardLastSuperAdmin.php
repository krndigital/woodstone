<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The system must always keep at least one active Super Admin.
 */
final class GuardLastSuperAdmin
{
    public static function check(User $target, string $field = 'role'): void
    {
        if ($target->role !== Role::SuperAdmin || ! $target->is_active) {
            return;
        }

        $others = User::query()
            ->where('role', Role::SuperAdmin->value)
            ->where('is_active', true)
            ->whereKeyNot($target->id)
            ->exists();

        if (! $others) {
            throw ValidationException::withMessages([
                $field => __('At least one active Super Admin must remain.'),
            ]);
        }
    }
}
