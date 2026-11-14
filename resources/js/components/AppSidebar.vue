<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChefHat,
    LayoutGrid,
    LayoutPanelTop,
    MonitorSmartphone,
    Settings2,
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
import { edit as settingsEdit } from '@/routes/admin/settings';
import { index as floorIndex } from '@/routes/floor';
import { index as kitchenIndex } from '@/routes/kitchen';
import { index as menuIndex } from '@/routes/menu';
import { index as posIndex } from '@/routes/pos';
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
