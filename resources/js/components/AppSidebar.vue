<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Scale,
    BookOpenCheck,
    Clock,
    IdCard,
    CalendarCheck,
    ChefHat,
    Wallet,
    HandCoins,
    ReceiptText,
    LayoutGrid,
    LayoutPanelTop,
    MonitorSmartphone,
    Package,
    Settings2,
    ShieldCheck,
    ShoppingCart,
    UtensilsCrossed,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as accountingIndex } from '@/routes/accounting';
import { edit as rolesEdit } from '@/routes/admin/roles';
import { edit as settingsEdit } from '@/routes/admin/settings';
import { index as billingIndex } from '@/routes/billing';
import { index as customersIndex } from '@/routes/customers';
import { index as floorIndex } from '@/routes/floor';
import { index as kitchenIndex } from '@/routes/kitchen';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as menuIndex } from '@/routes/menu';
import { index as staffIndex } from '@/routes/staff';
import { cashBook, day } from '@/routes/money';
import { index as expensesIndex } from '@/routes/money/expenses';
import { index as posIndex } from '@/routes/pos';
import { index as reportsIndex } from '@/routes/reports';
import { index as stockIndex } from '@/routes/stock/items';
import { index as terminalsIndex } from '@/routes/terminals';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const { can } = usePermissions();

// Menu entries appear only for users who may open them (the server checks again).
const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    ];

    if (can('pos.use')) {
        items.push({ title: 'POS', href: posIndex(), icon: ShoppingCart });
    }

    if (can('billing.take_payment')) {
        items.push({ title: 'Bills', href: billingIndex(), icon: ReceiptText });
    }

    if (can('dues.manage')) {
        items.push({
            title: 'Customers & dues',
            href: customersIndex(),
            icon: HandCoins,
        });
    }

    if (can('reports.view')) {
        items.push({ title: 'Reports', href: reportsIndex(), icon: BarChart3 });
    }

    if (can('cash.day')) {
        items.push({
            title: 'Day open / close',
            href: day(),
            icon: CalendarCheck,
        });
    }

    if (can('expenses.create')) {
        items.push({ title: 'Expenses', href: expensesIndex(), icon: Wallet });
    }

    if (can('settings.manage')) {
        items.push({
            title: 'Accounting',
            href: accountingIndex(),
            icon: Scale,
        });
    }

    if (can('cash.manage')) {
        items.push({
            title: 'Cash book',
            href: cashBook(),
            icon: BookOpenCheck,
        });
    }

    if (can('kitchen.view')) {
        items.push({ title: 'Kitchen', href: kitchenIndex(), icon: ChefHat });
    }

    if (can('floor.view')) {
        items.push({
            title: 'Tables',
            href: floorIndex(),
            icon: LayoutPanelTop,
        });
    }

    if (can('menu.view')) {
        items.push({ title: 'Menu', href: menuIndex(), icon: UtensilsCrossed });
    }

    if (can('stock.manage')) {
        items.push({ title: 'Stock', href: stockIndex(), icon: Package });
    }

    if (can('staff.attendance')) {
        items.push({
            title: 'Attendance',
            href: attendanceIndex(),
            icon: Clock,
        });
    }

    if (can('staff.manage')) {
        items.push({
            title: 'Staff & salary',
            href: staffIndex(),
            icon: IdCard,
        });
    }

    if (can('users.manage')) {
        items.push({ title: 'Users', href: usersIndex(), icon: Users });
    }

    if (can('terminals.manage')) {
        items.push({
            title: 'Terminals',
            href: terminalsIndex(),
            icon: MonitorSmartphone,
        });
    }

    if (can('settings.manage')) {
        items.push({
            title: 'Settings',
            href: settingsEdit(),
            icon: Settings2,
        });
        items.push({
            title: 'Roles & access',
            href: rolesEdit(),
            icon: ShieldCheck,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
