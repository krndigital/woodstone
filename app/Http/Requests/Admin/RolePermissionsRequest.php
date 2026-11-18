<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageSettings);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roles = array_values(array_map(fn (Role $r): string => $r->value, array_filter(Role::cases(), fn (Role $r): bool => $r !== Role::SuperAdmin)));
        $permissions = array_values(array_map(fn (Permission $p): string => $p->value, array_filter(Permission::cases(), fn (Permission $p): bool => $p !== Permission::ManageSettings)));

        return [
            'roles' => ['required', 'array:'.implode(',', $roles)],
            'roles.*' => ['array'],
            'roles.*.*' => ['string', Rule::in($permissions)],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function permissionsByRole(): array
    {
        $out = [];
        foreach (Role::cases() as $role) {
            if ($role === Role::SuperAdmin) {
                continue;
            }
            /** @var list<string> $given */
            $given = (array) $this->input("roles.{$role->value}", []);
            $out[$role->value] = array_values(array_unique(array_map('strval', $given)));
        }

        return $out;
    }
}
