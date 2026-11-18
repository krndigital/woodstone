<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { edit as rolesEdit } from '@/routes/admin/roles';

type RoleRow = {
    value: string;
    label: string;
    permissions: string[];
    defaults: string[];
    users: number;
};

const props = defineProps<{
    roles: RoleRow[];
    permissions: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles & access', href: rolesEdit() }] },
});

const form = useForm({
    roles: Object.fromEntries(
        props.roles.map((r) => [r.value, [...r.permissions]]),
    ) as Record<string, string[]>,
});

function has(role: string, perm: string): boolean {
    return form.roles[role]?.includes(perm) ?? false;
}
function toggle(role: string, perm: string): void {
    const list = form.roles[role] ?? [];
    form.roles[role] = list.includes(perm)
        ? list.filter((p) => p !== perm)
        : [...list, perm];
}
function resetRole(r: RoleRow): void {
    form.roles[r.value] = [...r.defaults];
}
function save(): void {
    form.submit(RoleController.update(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Roles & access" />
    <form class="flex flex-col gap-4 p-4" @submit.prevent="save">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Roles &amp; access
            </h1>
            <p class="text-sm text-muted-foreground">
                Tick what each role may do. Super Admin can always do
                everything, and only Super Admin can change Settings. Staff see
                the change on their next screen.
            </p>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Can…</th>
                        <th
                            v-for="r in roles"
                            :key="r.value"
                            class="p-3 text-center"
                        >
                            {{ r.label }}
                            <div
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ r.users }} user(s)
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in permissions"
                        :key="p.value"
                        class="border-t hover:bg-muted/40"
                    >
                        <td class="p-3">{{ p.label }}</td>
                        <td
                            v-for="r in roles"
                            :key="r.value"
                            class="p-3 text-center"
                        >
                            <input
                                type="checkbox"
                                class="size-5"
                                :checked="has(r.value, p.value)"
                                :aria-label="`${r.label}: ${p.label}`"
                                @change="toggle(r.value, p.value)"
                            />
                        </td>
                    </tr>
                    <tr class="border-t bg-muted/40">
                        <td class="p-3 text-xs text-muted-foreground">
                            Back to the standard set
                        </td>
                        <td
                            v-for="r in roles"
                            :key="r.value"
                            class="p-3 text-center"
                        >
                            <button
                                type="button"
                                class="text-xs font-semibold underline"
                                @click="resetRole(r)"
                            >
                                Default
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <InputError :message="form.errors.roles" />
        <div>
            <Button type="submit" class="h-11 px-6" :disabled="form.processing"
                >Save roles &amp; access</Button
            >
        </div>
    </form>
</template>
