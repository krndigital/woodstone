<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\Permission;
use App\Models\User;

/**
 * The list of reports (requirements §6) and who may see each one.
 */
final class ReportCatalog
{
    /**
     * @return array<string, array{title: string, description: string, group: string, permission: Permission}>
     */
    public static function all(): array
    {
        $r = Permission::ViewReports;
        $sa = Permission::ManageSettings;

        return [
            'daily-sales' => ['title' => 'Daily sales', 'description' => 'Sales per day by payment mode, discount, service charge, VAT and returns', 'group' => 'Sales', 'permission' => $r],
            'items' => ['title' => 'Items: sold, cost and profit', 'description' => 'Quantity, revenue, food cost, profit and margin per item (most and least ordered)', 'group' => 'Sales', 'permission' => $r],
            'categories' => ['title' => 'Category revenue', 'description' => 'Revenue per menu category', 'group' => 'Sales', 'permission' => $r],
            'hourly' => ['title' => 'Hourly sales', 'description' => 'Bills and sales by hour of day (peak hours)', 'group' => 'Sales', 'permission' => $r],
            'tables' => ['title' => 'Table / cabin performance', 'description' => 'Revenue, covers and average bill per seat, takeaway and delivery', 'group' => 'Sales', 'permission' => $r],
            'sales-vs-purchase' => ['title' => 'Sales vs purchase', 'description' => 'Sales against purchases per day', 'group' => 'Sales', 'permission' => $r],
            'expenses' => ['title' => 'Expenses', 'description' => 'By category, operating vs investment', 'group' => 'Money', 'permission' => $r],
            'cash-book' => ['title' => 'Cash book', 'description' => 'Opening, in, out and closing per account', 'group' => 'Money', 'permission' => $r],
            'day-close' => ['title' => 'Day close', 'description' => 'Expected vs counted cash and difference', 'group' => 'Money', 'permission' => $r],
            'customer-credit' => ['title' => 'Customer credit', 'description' => 'Balance and ageing per customer', 'group' => 'Money', 'permission' => $r],
            'supplier-payable' => ['title' => 'Supplier payable', 'description' => 'What we owe each supplier', 'group' => 'Money', 'permission' => $r],
            'payables-ageing' => ['title' => 'Payables ageing', 'description' => 'Not due, 1–30, 31–60, 61–90, 90+ days late, and due in the next 7 days', 'group' => 'Money', 'permission' => $r],
            'stock' => ['title' => 'Stock', 'description' => 'Current stock and value, usage, wastage in the period', 'group' => 'Stock', 'permission' => $r],
            'kitchen-times' => ['title' => 'Kitchen times', 'description' => 'Average minutes from KOT to ready and to served, per station', 'group' => 'Staff', 'permission' => $r],
            'service-calls' => ['title' => 'Guest calls', 'description' => 'Calls per seat and average response time per waiter', 'group' => 'Staff', 'permission' => $r],
            'staff' => ['title' => 'Staff', 'description' => 'Attendance and sales per waiter', 'group' => 'Staff', 'permission' => $r],
            'profit-loss' => ['title' => 'Profit and loss', 'description' => 'Income, cost of goods, expenses, net profit; kitchen and bar P/L', 'group' => 'Owner', 'permission' => $sa],
            'trial-balance' => ['title' => 'Trial balance', 'description' => 'Every ledger account with debit and credit totals for the period', 'group' => 'Owner', 'permission' => $sa],
            'balance-sheet' => ['title' => 'Balance sheet', 'description' => 'Assets against liabilities and owner equity on the end date', 'group' => 'Owner', 'permission' => $sa],
            'cash-flow' => ['title' => 'Cash flow statement', 'description' => 'Operating, investing and financing cash for the period', 'group' => 'Owner', 'permission' => $sa],
            'audit' => ['title' => 'Voids, discounts and audit', 'description' => 'Every void, discount, return, reprint and failed approval', 'group' => 'Owner', 'permission' => $sa],
        ];
    }

    public static function allows(User $user, string $key): bool
    {
        $report = self::all()[$key] ?? null;

        return $report !== null && $user->hasPermission($report['permission']);
    }
}
