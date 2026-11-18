<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Settings;
use Throwable;

/**
 * Predefined roles (requirements §4, D-06). Each role has default permissions; the owner can
 * change them for every role except Super Admin (Settings → Roles & access).
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';
    case Bartender = 'bartender';
    case StoreKeeper = 'store_keeper';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Cashier => 'Cashier',
            self::Waiter => 'Waiter',
            self::Kitchen => 'Kitchen',
            self::Bartender => 'Bartender',
            self::StoreKeeper => 'Store keeper',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        if ($this === self::SuperAdmin) {
            return Permission::cases();
        }

        // The owner can change what each role may do (Settings → Roles & access).
        $override = self::overrides()[$this->value] ?? null;
        if (is_array($override)) {
            $list = [];
            foreach (Permission::cases() as $p) {
                if ($p !== Permission::ManageSettings && in_array($p->value, $override, true)) {
                    $list[] = $p;
                }
            }

            return $list;
        }

        return $this->defaultPermissions();
    }

    /**
     * What each role may do out of the box.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Admin => self::allExcept(Permission::ManageSettings),
            self::Cashier => [
                Permission::ViewFloor, Permission::UsePos, Permission::TakePayment, Permission::GiveDiscount,
                Permission::ManageDues, Permission::CreateExpense, Permission::CloseDay,
                Permission::ViewMenu, Permission::SetItemAvailability, Permission::RecordAttendance,
            ],
            self::Waiter => [Permission::ViewFloor, Permission::UsePos, Permission::ViewMenu],
            self::Kitchen, self::Bartender => [Permission::ViewKitchen, Permission::ViewMenu, Permission::SetItemAvailability],
            self::StoreKeeper => [Permission::ManageStock, Permission::CreateExpense],
        };
    }

    /**
     * @return list<Permission>
     */
    private static function allExcept(Permission $excluded): array
    {
        $permissions = [];
        foreach (Permission::cases() as $permission) {
            if ($permission !== $excluded) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * @return array<string, mixed>
     */
    private static function overrides(): array
    {
        try {
            $value = Settings::get('role_permissions');
        } catch (Throwable) {
            return [];
        }

        return is_array($value) ? $value : [];
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Super Admin must always sign in with a password (D-06).
     */
    public function canUsePin(): bool
    {
        return $this !== self::SuperAdmin;
    }

    /**
     * Whether a user with this role may create, edit or deactivate a user with $target role.
     * Only a Super Admin may touch Super Admin accounts.
     */
    public function canManageRole(self $target): bool
    {
        return match ($this) {
            self::SuperAdmin => true,
            self::Admin => $target !== self::SuperAdmin,
            default => false,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $r): array => ['value' => $r->value, 'label' => $r->label()],
            self::cases(),
        );
    }
}
