<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BellRing, CheckCheck, RefreshCw } from '@lucide/vue';
import ServiceCallController from '@/actions/App/Http/Controllers/Guest/ServiceCallController';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import KitchenController from '@/actions/App/Http/Controllers/Kitchen/KitchenController';
import { Button } from '@/components/ui/button';
import { minutesSince } from '@/lib/money';
import { index as kitchenIndex } from '@/routes/kitchen';

type Item = {
    id: number;
    name: string;
    quantity: number;
    modifiers: string[];
    note: string | null;
    status: 'sent' | 'preparing' | 'ready' | 'served' | 'void';
};
type Ticket = {
    id: number;
    number: number;
    station: string;
    order_label: string;
    order_number: string;
    order_type: string;
    table_id: number | null;
    note: string | null;
    waiter: string;
    created_at: string | null;
    items: Item[];
};

const props = defineProps<{
    kots: Ticket[];
    stations: { id: number; name: string }[];
    stationId: number | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Kitchen', href: kitchenIndex() }] },
});

const now = ref(Date.now());
let poll: ReturnType<typeof setInterval> | undefined;
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    poll = setInterval(
        () => !document.hidden && router.reload({ only: ['kots'] }),
        12000,
    );
    clock = setInterval(() => (now.value = Date.now()), 30000);
});
onBeforeUnmount(() => {
    clearInterval(poll);
    clearInterval(clock);
});

function age(iso: string | null): number {
    void now.value;
    return iso === null
        ? 0
        : Math.round((Date.now() - new Date(iso).getTime()) / 60000);
}
function chooseStation(id: number | null): void {
    router.visit(kitchenIndex({ query: id === null ? {} : { station: id } }), {
        preserveScroll: true,
    });
}
function bump(item: Item): void {
    if (item.status !== 'sent' && item.status !== 'preparing') return;
    router.visit(KitchenController.bump(item.id), {
        preserveScroll: true,
        only: ['kots'],
    });
}
const pinged = ref<number | null>(null);
async function ping(t: Ticket): Promise<void> {
    if (t.table_id === null) return;
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    await fetch(ServiceCallController.ping().url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': m ? decodeURIComponent(m[1]) : '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            dining_table_id: t.table_id,
            note: `Food ready · KOT ${t.number}`,
        }),
    });
    pinged.value = t.id;
}
function bumpAll(t: Ticket): void {
    router.visit(KitchenController.bumpKot(t.id), {
        preserveScroll: true,
        only: ['kots'],
    });
}

const ITEM: Record<Item['status'], string> = {
    sent: 'bg-background',
    preparing: 'bg-orange-100 dark:bg-orange-900/40',
    ready: 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
    served: 'opacity-40',
    void: 'bg-red-100 text-red-800 line-through dark:bg-red-900/40 dark:text-red-200',
};
const LABEL: Record<Item['status'], string> = {
    sent: 'New',
    preparing: 'Cooking',
    ready: 'Ready',
    served: 'Served',
    void: 'VOID',
};
</script>

<template>
    <Head title="Kitchen" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="mr-2 text-2xl font-bold tracking-tight">
                Kitchen display
            </h1>
            <button
                v-for="s in [{ id: null, name: 'All stations' }, ...stations]"
                :key="s.id ?? 0"
                type="button"
                class="h-10 rounded-full border px-4 text-sm font-semibold"
                :class="
                    props.stationId === s.id
                        ? 'border-foreground bg-foreground text-background'
                        : 'hover:bg-muted'
                "
                @click="chooseStation(s.id)"
            >
                {{ s.name }}
            </button>
            <Button
                variant="outline"
                class="ml-auto h-10"
                @click="router.reload({ only: ['kots'] })"
                ><RefreshCw class="size-4" /> Refresh</Button
            >
        </div>
        <p class="-mt-2 text-sm text-muted-foreground">
            Tap an item: New → Cooking → Ready. Updates every 12 seconds.
        </p>

        <p
            v-if="kots.length === 0"
            class="py-20 text-center text-lg text-muted-foreground"
        >
            No tickets waiting. 👍
        </p>

        <div
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <article
                v-for="t in kots"
                :key="t.id"
                class="flex flex-col overflow-hidden rounded-xl border-2"
                :class="
                    age(t.created_at) >= 20
                        ? 'border-red-500'
                        : age(t.created_at) >= 10
                          ? 'border-amber-400'
                          : 'border-border'
                "
            >
                <header
                    class="flex items-start justify-between gap-2 bg-muted px-3 py-2"
                >
                    <div>
                        <div class="text-lg font-bold">{{ t.order_label }}</div>
                        <div class="text-xs text-muted-foreground">
                            KOT {{ t.number }} · {{ t.station }} ·
                            {{ t.waiter }}
                        </div>
                    </div>
                    <span
                        class="rounded-md px-2 py-1 text-sm font-bold tabular-nums"
                        :class="
                            age(t.created_at) >= 20
                                ? 'bg-red-600 text-white'
                                : age(t.created_at) >= 10
                                  ? 'bg-amber-400 text-black'
                                  : 'bg-background'
                        "
                    >
                        {{ minutesSince(t.created_at) }}
                    </span>
                </header>
                <ul class="flex flex-1 flex-col divide-y">
                    <li v-for="i in t.items" :key="i.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 px-3 py-3 text-left"
                            :class="ITEM[i.status]"
                            :disabled="
                                i.status !== 'sent' && i.status !== 'preparing'
                            "
                            @click="bump(i)"
                        >
                            <span class="text-xl font-bold tabular-nums"
                                >{{ i.quantity }}×</span
                            >
                            <span class="flex-1">
                                <span class="text-lg font-semibold">{{
                                    i.name
                                }}</span>
                                <span
                                    v-for="m in i.modifiers"
                                    :key="m"
                                    class="block text-sm"
                                    >+ {{ m }}</span
                                >
                                <span
                                    v-if="i.note"
                                    class="block text-sm font-semibold text-orange-700 dark:text-orange-300"
                                    >» {{ i.note }}</span
                                >
                            </span>
                            <span class="text-xs font-bold uppercase">{{
                                LABEL[i.status]
                            }}</span>
                        </button>
                    </li>
                </ul>
                <p v-if="t.note" class="border-t px-3 py-2 text-sm">
                    Note: {{ t.note }}
                </p>
                <div class="m-2 flex gap-2">
                    <Button class="h-12 flex-1 text-base" @click="bumpAll(t)"
                        ><CheckCheck class="size-5" /> All ready</Button
                    >
                    <Button
                        v-if="t.table_id"
                        variant="outline"
                        class="h-12"
                        :disabled="pinged === t.id"
                        @click="ping(t)"
                        ><BellRing class="size-5" />
                        {{ pinged === t.id ? 'Pinged' : 'Ping waiter' }}</Button
                    >
                </div>
            </article>
        </div>
    </div>
</template>
