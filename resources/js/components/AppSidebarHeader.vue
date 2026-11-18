<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { logout } from '@/routes';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const { resolvedAppearance, updateAppearance } = useAppearance();
function toggleTheme(): void {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <button
                type="button"
                class="flex size-10 items-center justify-center rounded-lg border hover:bg-muted"
                :aria-label="
                    resolvedAppearance === 'dark'
                        ? 'Switch to light theme'
                        : 'Switch to dark theme'
                "
                @click="toggleTheme"
            >
                <Sun v-if="resolvedAppearance === 'dark'" class="size-5" />
                <Moon v-else class="size-5" />
            </button>
            <div
                v-if="user"
                class="hidden text-right text-xs leading-tight sm:block"
            >
                <div class="font-semibold">{{ user.name }}</div>
                <div class="text-muted-foreground">{{ user.role_label }}</div>
            </div>
            <Link
                v-if="user"
                :href="logout()"
                as="button"
                class="flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-semibold text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                aria-label="Log out"
                data-test="header-logout"
                @click="router.flushAll()"
            >
                <LogOut class="size-4" />
                <span class="hidden md:inline">Log out</span>
            </Link>
        </div>
    </header>
</template>
