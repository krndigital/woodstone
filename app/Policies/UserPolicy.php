<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    /**
     * Admins manage everyone except Super Admins; only a Super Admin manages Super Admins.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::ManageUsers)
            && $actor->role->canManageRole($target->role);
    }
}
