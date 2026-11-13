<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Predefined roles (requirements §4, D-06). Permissions are fixed per role in step 1;
 * an editable permission matrix comes with Settings (step 2).
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
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Admin => array_values(array_filter(
                Permission::cases(),
                fn (Permission $p): bool => $p !== Permission::ManageSettings,
            )),
            self::Cashier => [
                Permission::UsePos, Permission::TakePayment, Permission::GiveDiscount,
                Permission::ManageDues, Permission::CreateExpense,
                Permission::ViewMenu, Permission::SetItemAvailability,
            ],
            self::Waiter => [Permission::UsePos, Permission::ViewMenu],
            self::Kitchen, self::Bartender => [Permission::ViewKitchen, Permission::ViewMenu, Permission::SetItemAvailability],
            self::StoreKeeper => [Permission::ManageStock, Permission::CreateExpense],
        };
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
