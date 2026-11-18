<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RolePermissionsRequest;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roles & access: the owner ticks what each role may do. Super Admin always has everything and
 * "Settings" stays Super Admin only. Saved as one setting, so the change is audited with before/after.
 */
class RoleController extends Controller
{
    public function edit(): Response
    {
        $roles = array_values(array_filter(Role::cases(), fn (Role $r): bool => $r !== Role::SuperAdmin));

        return Inertia::render('admin/Roles', [
            'roles' => array_map(fn (Role $r): array => [
                'value' => $r->value,
                'label' => $r->label(),
                'permissions' => array_map(fn (Permission $p): string => $p->value, $r->permissions()),
                'defaults' => array_map(fn (Permission $p): string => $p->value, $r->defaultPermissions()),
                'users' => User::query()->where('role', $r->value)->count(),
            ], $roles),
            'permissions' => array_values(array_map(
                fn (Permission $p): array => ['value' => $p->value, 'label' => $p->label()],
                array_filter(Permission::cases(), fn (Permission $p): bool => $p !== Permission::ManageSettings),
            )),
        ]);
    }

    public function update(RolePermissionsRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        Settings::save(['role_permissions' => $request->permissionsByRole()], $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roles & access saved. Staff see the change on their next screen.')]);

        return back();
    }
}
