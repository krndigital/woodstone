<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Permission;
use App\Enums\Role;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    public function test_super_admin_has_every_permission_and_no_pin(): void
    {
        foreach (Permission::cases() as $permission) {
            $this->assertTrue(Role::SuperAdmin->allows($permission));
        }
        $this->assertFalse(Role::SuperAdmin->canUsePin());
    }

    public function test_admin_cannot_change_app_settings_or_manage_super_admins(): void
    {
        $this->assertFalse(Role::Admin->allows(Permission::ManageSettings));
        $this->assertTrue(Role::Admin->allows(Permission::ManageUsers));
        $this->assertFalse(Role::Admin->canManageRole(Role::SuperAdmin));
        $this->assertTrue(Role::Admin->canManageRole(Role::Cashier));
    }

    public function test_staff_roles_are_limited(): void
    {
        $this->assertSame([Permission::UsePos], Role::Waiter->permissions());
        $this->assertSame([Permission::ViewKitchen], Role::Kitchen->permissions());
        $this->assertFalse(Role::Cashier->allows(Permission::ViewReports));
        $this->assertFalse(Role::Cashier->canManageRole(Role::Waiter));
    }
}
