<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { MonitorSmartphone, ShieldCheck, Users } from '@lucide/vue';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { edit as editSecurity } from '@/routes/security';
import { index as terminalsIndex } from '@/routes/terminals';
import { index as usersIndex } from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { can } = usePermissions();
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Welcome, {{ user.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Signed in as {{ user.role_label }}. POS, tables, kitchen and
                reports are added step by step.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-if="can('users.manage')"
                :href="usersIndex()"
                class="flex flex-col gap-2 rounded-xl border p-5 transition hover:border-primary hover:bg-primary/5"
            >
                <Users class="size-6 text-primary" />
                <span class="font-semibold">Users & logins</span>
                <span class="text-sm text-muted-foreground"
                    >Add staff, set roles, reset passwords and PINs.</span
                >
            </Link>
            <Link
                v-if="can('terminals.manage')"
                :href="terminalsIndex()"
                class="flex flex-col gap-2 rounded-xl border p-5 transition hover:border-primary hover:bg-primary/5"
            >
                <MonitorSmartphone class="size-6 text-primary" />
                <span class="font-semibold">Terminals</span>
                <span class="text-sm text-muted-foreground"
                    >Register the counter PC or tablets for quick PIN.</span
                >
            </Link>
            <Link
                :href="editSecurity()"
                class="flex flex-col gap-2 rounded-xl border p-5 transition hover:border-primary hover:bg-primary/5"
            >
                <ShieldCheck class="size-6 text-primary" />
                <span class="font-semibold">My password & PIN</span>
                <span class="text-sm text-muted-foreground"
                    >Change your password or set a quick PIN.</span
                >
            </Link>
        </div>
    </div>
</template>
