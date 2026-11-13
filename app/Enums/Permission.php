<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every permission the app checks. Registered as Gates in AppServiceProvider,
 * so routes use `can:<value>` middleware and Vue receives the user's list.
 */
enum Permission: string
{
    case ViewDashboard = 'dashboard.view';
    case UsePos = 'pos.use';
    case TakePayment = 'billing.take_payment';
    case GiveDiscount = 'billing.discount';
    case ApproveOverride = 'billing.approve_override';
    case ViewKitchen = 'kitchen.view';
    case ManageDues = 'dues.manage';
    case CreateExpense = 'expenses.create';
    case ManageStock = 'stock.manage';
    case ViewMenu = 'menu.view';
    case SetItemAvailability = 'menu.availability';
    case ManageMenu = 'menu.manage';
    case ViewReports = 'reports.view';
    case ManageStaff = 'staff.manage';
    case ManageUsers = 'users.manage';
    case ManageTerminals = 'terminals.manage';
    case ManageSettings = 'settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::ViewDashboard => 'See owner dashboard',
            self::UsePos => 'Take orders and send KOT',
            self::TakePayment => 'Take payment and print bill',
            self::GiveDiscount => 'Give discount (within limit)',
            self::ApproveOverride => 'Approve big discount or void item',
            self::ViewKitchen => 'Kitchen and bar display',
            self::ManageDues => 'Put bill on due, receive dues',
            self::CreateExpense => 'Add expenses',
            self::ManageStock => 'Stock and purchase orders',
            self::ViewMenu => 'See menu and prices',
            self::SetItemAvailability => 'Mark items sold out / back',
            self::ManageMenu => 'Edit menu and prices',
            self::ViewReports => 'See reports and profit',
            self::ManageStaff => 'Staff and payroll',
            self::ManageUsers => 'Manage users and logins',
            self::ManageTerminals => 'Register terminals for PIN',
            self::ManageSettings => 'Tax, payment and app settings',
        };
    }
}
