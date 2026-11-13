<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { MonitorSmartphone } from '@lucide/vue';
import TerminalController from '@/actions/App/Http/Controllers/TerminalController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as terminalsIndex } from '@/routes/terminals';

type Row = {
    id: number;
    name: string;
    registered_by: string | null;
    created_at: string | null;
    last_seen_at: string | null;
    last_ip: string | null;
    revoked: boolean;
    is_this_device: boolean;
};

defineProps<{
    terminals: Row[];
    thisDeviceRegistered: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Terminals', href: terminalsIndex() }],
    },
});

const form = useForm({ name: '' });

function register(): void {
    form.submit(TerminalController.store(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function revoke(row: Row): void {
    if (window.confirm(`Stop quick PIN on "${row.name}"?`)) {
        router.visit(TerminalController.destroy(row.id), {
            preserveScroll: true,
        });
    }
}

function when(iso: string | null): string {
    return iso === null
        ? '—'
        : new Date(iso).toLocaleString('en-GB', {
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          });
}
</script>

<template>
    <Head title="Terminals" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            title="Terminals"
            description="Shared devices (counter PC, garden tablet, kitchen screen) where staff can switch user with their quick PIN."
        />

        <section
            class="flex flex-col gap-3 rounded-xl border p-4"
            :class="
                thisDeviceRegistered ? 'bg-green-50 dark:bg-green-950/30' : ''
            "
        >
            <div class="flex items-center gap-2 font-semibold">
                <MonitorSmartphone class="size-5" />
                This device
            </div>
            <p v-if="thisDeviceRegistered" class="text-sm">
                This device is registered. Staff can tap “Switch user” and enter
                their PIN.
            </p>
            <form
                v-else
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="register"
            >
                <div class="grid gap-2">
                    <Label for="t-name">Name for this device</Label>
                    <Input
                        id="t-name"
                        v-model="form.name"
                        placeholder="e.g. Counter PC"
                        class="h-11 w-64"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <Button type="submit" class="h-11" :disabled="form.processing"
                    >Register this device</Button
                >
            </form>
        </section>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Device</th>
                        <th class="px-4 py-3 font-medium">Registered</th>
                        <th class="px-4 py-3 font-medium">Last seen</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in terminals" :key="row.id" class="border-t">
                        <td class="px-4 py-3 font-semibold">
                            {{ row.name }}
                            <span
                                v-if="row.is_this_device"
                                class="text-xs font-normal text-muted-foreground"
                                >(this device)</span
                            >
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ when(row.created_at) }} ·
                            {{ row.registered_by ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ when(row.last_seen_at) }}
                            <span v-if="row.last_ip">· {{ row.last_ip }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="row.revoked ? 'outline' : 'default'"
                                >{{ row.revoked ? 'Revoked' : 'Active' }}</Badge
                            >
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                v-if="!row.revoked"
                                size="sm"
                                variant="outline"
                                @click="revoke(row)"
                                >Revoke</Button
                            >
                        </td>
                    </tr>
                    <tr v-if="terminals.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No devices registered yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
