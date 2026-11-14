<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Bike, Plus, RefreshCw, ShoppingBag, Users } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BillController from '@/actions/App/Http/Controllers/Billing/BillController';
import PosController from '@/actions/App/Http/Controllers/Pos/PosController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { minutesSince, rs } from '@/lib/money';
import { index as posIndex } from '@/routes/pos';

type Zone = { id: number; name: string; is_active: boolean };
type Table = {
    id: number;
    zone_id: number;
    code: string;
    label: string;
    kind: 'table' | 'cabin';
    seats: number;
    status: 'free' | 'occupied' | 'bill' | 'reserved' | 'cleaning';
    status_label: string;
    reserved_for: string | null;
};
type OpenOrder = {
    id: number;
    number: string;
    label: string;
    type: 'dine_in' | 'takeaway' | 'delivery';
    table_id: number | null;
    guests: number;
    waiter: string;
    customer_name: string | null;
    subtotal: string;
    items_count: number;
    opened_at: string;
};

const props = defineProps<{
    zones: Zone[];
    tables: Table[];
    orders: OpenOrder[];
    can: { pay: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'POS', href: posIndex() }] },
});

const STYLE: Record<Table['status'], string> = {
    free: 'border-green-300 bg-green-50 text-green-800 dark:bg-green-950/40 dark:text-green-200',
    occupied:
        'border-orange-300 bg-orange-50 text-orange-800 dark:bg-orange-950/40 dark:text-orange-200',
    bill: 'border-blue-300 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200',
    reserved:
        'border-violet-300 bg-violet-50 text-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
    cleaning:
        'border-slate-300 bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300',
};

const orderByTable = computed(() => {
    const map = new Map<number, OpenOrder>();
    props.orders.forEach((o) => o.table_id !== null && map.set(o.table_id, o));
    return map;
});
const counterOrders = computed(() =>
    props.orders.filter((o) => o.type !== 'dine_in'),
);

let timer: ReturnType<typeof setInterval> | undefined;
let off: (() => void) | undefined;
onMounted(() => {
    // After payment the server asks for the bill to be printed.
    off = router.on('flash', (event) => {
        const id = (event as CustomEvent).detail?.flash?.printBill as
            | number
            | undefined;
        if (id)
            window.open(
                BillController.print(id).url,
                '_blank',
                'width=380,height=700',
            );
    });
    timer = setInterval(
        () => !document.hidden && router.reload({ only: ['tables', 'orders'] }),
        15000,
    );
});
onBeforeUnmount(() => {
    clearInterval(timer);
    off?.();
});

/* open a new order */
const sheetOpen = ref(false);
const form = useForm({
    type: 'dine_in' as OpenOrder['type'],
    dining_table_id: null as number | null,
    guests: 2,
    customer_name: '',
    customer_phone: '',
    delivery_address: '',
    note: '',
});
const chosenTable = computed(
    () => props.tables.find((t) => t.id === form.dining_table_id) ?? null,
);

function tap(t: Table): void {
    const existing = orderByTable.value.get(t.id);
    if (existing) {
        router.visit(PosController.show(existing.id));
        return;
    }
    if (t.status !== 'free' && t.status !== 'reserved') return;
    form.reset();
    form.clearErrors();
    form.type = 'dine_in';
    form.dining_table_id = t.id;
    form.guests = Math.min(t.seats, 2);
    sheetOpen.value = true;
}

function newCounter(type: 'takeaway' | 'delivery'): void {
    form.reset();
    form.clearErrors();
    form.type = type;
    form.dining_table_id = null;
    form.guests = 1;
    sheetOpen.value = true;
}

function open(): void {
    form.submit(PosController.store(), {
        onSuccess: () => (sheetOpen.value = false),
    });
}
</script>

<template>
    <Head title="POS" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Orders</h1>
                <p class="text-sm text-muted-foreground">
                    Tap a table to open or continue its order · updates every 15
                    seconds
                </p>
            </div>
            <div class="ml-auto flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    class="h-11"
                    @click="router.reload({ only: ['tables', 'orders'] })"
                    ><RefreshCw class="size-4" /> Refresh</Button
                >
                <Button
                    variant="outline"
                    class="h-11"
                    @click="newCounter('takeaway')"
                    ><ShoppingBag class="size-4" /> New takeaway</Button
                >
                <Button
                    variant="outline"
                    class="h-11"
                    @click="newCounter('delivery')"
                    ><Bike class="size-4" /> New delivery</Button
                >
            </div>
        </div>

        <section v-if="counterOrders.length" class="flex flex-col gap-2">
            <h2
                class="text-sm font-bold tracking-wide text-muted-foreground uppercase"
            >
                Takeaway & delivery
            </h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <Link
                    v-for="o in counterOrders"
                    :key="o.id"
                    :href="PosController.show(o.id).url"
                    class="flex flex-col gap-1 rounded-xl border border-orange-300 bg-orange-50 p-3 text-orange-900 dark:bg-orange-950/40 dark:text-orange-100"
                >
                    <span class="font-bold">{{ o.label }}</span>
                    <span class="text-xs"
                        >{{ o.customer_name ?? '—' }} ·
                        {{ o.items_count }} items</span
                    >
                    <span
                        class="mt-auto flex justify-between text-sm font-semibold tabular-nums"
                        ><span>{{ rs(o.subtotal) }}</span
                        ><span class="text-xs opacity-70">{{
                            minutesSince(o.opened_at)
                        }}</span></span
                    >
                </Link>
            </div>
        </section>

        <section
            v-for="zone in zones.filter((z) => z.is_active)"
            :key="zone.id"
            class="flex flex-col gap-2"
        >
            <h2
                class="text-sm font-bold tracking-wide text-muted-foreground uppercase"
            >
                {{ zone.name }}
            </h2>
            <div
                class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8"
            >
                <button
                    v-for="t in tables.filter((x) => x.zone_id === zone.id)"
                    :key="t.id"
                    type="button"
                    class="flex min-h-24 flex-col items-start gap-0.5 rounded-xl border-2 p-3 text-left transition active:scale-95 disabled:opacity-60"
                    :class="STYLE[t.status]"
                    :disabled="
                        t.status === 'cleaning' && !orderByTable.get(t.id)
                    "
                    @click="tap(t)"
                >
                    <span class="text-lg font-bold">{{ t.code }}</span>
                    <template v-if="orderByTable.get(t.id)">
                        <span class="text-sm font-semibold tabular-nums">{{
                            rs(orderByTable.get(t.id)!.subtotal)
                        }}</span>
                        <span class="text-xs opacity-80"
                            >{{ orderByTable.get(t.id)!.waiter }} ·
                            {{
                                minutesSince(orderByTable.get(t.id)!.opened_at)
                            }}</span
                        >
                    </template>
                    <template v-else>
                        <span class="flex items-center gap-1 text-xs"
                            ><Users class="size-3" /> {{ t.seats }}</span
                        >
                        <span class="text-xs">{{
                            t.status === 'reserved'
                                ? t.reserved_for
                                : t.status_label
                        }}</span>
                    </template>
                </button>
            </div>
        </section>
    </div>

    <Sheet v-model:open="sheetOpen">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <SheetHeader>
                <SheetTitle>
                    {{
                        form.type === 'dine_in'
                            ? `New order · ${chosenTable?.label ?? ''}`
                            : form.type === 'takeaway'
                              ? 'New takeaway'
                              : 'New delivery'
                    }}
                </SheetTitle>
                <SheetDescription
                    >Then add items and send KOT.</SheetDescription
                >
            </SheetHeader>
            <form class="flex flex-col gap-4 px-4" @submit.prevent="open">
                <div v-if="form.type === 'dine_in'" class="grid gap-2">
                    <Label>Guests</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="n in [1, 2, 3, 4, 5, 6, 8, 10]"
                            :key="n"
                            type="button"
                            class="size-11 rounded-lg border font-bold"
                            :class="
                                form.guests === n
                                    ? 'border-foreground bg-foreground text-background'
                                    : ''
                            "
                            @click="form.guests = n"
                        >
                            {{ n }}
                        </button>
                    </div>
                    <InputError
                        :message="
                            form.errors.guests || form.errors.dining_table_id
                        "
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="cname"
                        >Customer name
                        {{ form.type === 'dine_in' ? '(optional)' : '' }}</Label
                    >
                    <Input id="cname" v-model="form.customer_name" />
                    <InputError :message="form.errors.customer_name" />
                </div>
                <div v-if="form.type !== 'dine_in'" class="grid gap-2">
                    <Label for="cphone"
                        >Phone
                        {{
                            form.type === 'takeaway' ? '(optional)' : ''
                        }}</Label
                    >
                    <Input
                        id="cphone"
                        v-model="form.customer_phone"
                        inputmode="tel"
                    />
                    <InputError :message="form.errors.customer_phone" />
                </div>
                <div v-if="form.type === 'delivery'" class="grid gap-2">
                    <Label for="addr">Delivery address</Label>
                    <Input id="addr" v-model="form.delivery_address" />
                    <InputError :message="form.errors.delivery_address" />
                </div>
                <div class="grid gap-2">
                    <Label for="note">Note (optional)</Label>
                    <Input id="note" v-model="form.note" />
                </div>
                <Button
                    type="submit"
                    class="h-12 text-base"
                    :disabled="form.processing"
                    ><Plus class="size-4" /> Open order</Button
                >
            </form>
        </SheetContent>
    </Sheet>
</template>
