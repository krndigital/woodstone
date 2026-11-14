<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Manager approval for voids and big discounts. A user who has the permission approves their own
 * action; anyone else must enter a manager's username and PIN or password on the spot.
 * Routes that call this are rate-limited. Failed attempts are audited.
 */
final class ManagerApproval
{
    public static function resolve(User $actor, ?string $username, ?string $secret, Permission $permission = Permission::ApproveOverride): User
    {
        if ($actor->hasPermission($permission)) {
            return $actor;
        }

        $username = mb_strtolower(trim((string) $username));
        $secret = (string) $secret;

        if ($username === '' || $secret === '') {
            throw ValidationException::withMessages(['approver_username' => __('A manager must approve this. Enter their username and PIN or password.')]);
        }

        $approver = User::query()->where('username', $username)->first();

        $ok = $approver instanceof User
            && $approver->hasPermission($permission)
            && (
                ($approver->role->canUsePin() && $approver->hasPin() && ! $approver->pinIsLocked() && Hash::check($secret, (string) $approver->pin))
                || Hash::check($secret, $approver->password)
            );

        if (! $ok) {
            Audit::record('approval.failed', $approver, ['username' => $username, 'permission' => $permission->value], $actor);

            throw ValidationException::withMessages(['approver_username' => __('Approval failed. Check the manager username and PIN or password.')]);
        }

        return $approver;
    }
}
