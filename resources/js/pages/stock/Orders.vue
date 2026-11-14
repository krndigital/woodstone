<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Printer, Send, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Stock/PurchaseOrderController';
import InputError from '@/components/InputError.vue';
import StockTabs from '@/components/stock/StockTabs.vue';
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
import { rs, toPaisa } from '@/lib/money';
import { index as ordersIndex } from '@/routes/stock/orders';

type Order = {
    id: number;
    number: string;
    supplier: string;
    status: 'draft' | 'sent' | 'partial' | 'received' | 'cancelled';
    status_label: string;
    expected_on: string | null;
    note: string | null;
    total: string;
    lines: {
        name: string;
        unit: string;
        quantity: string;
        received_quantity: string;
        expected_rate: string;
    }[];
};
type StockItem = {
    id: number;
    name: string;
    unit: string;
    purchase_unit: string | null;
    avg_cost: string;
    purchase_factor: string | null;
};

const props = defineProps<{
    orders: Order[];
    suppliers: { id: number; name: string }[];
    items: StockItem[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stock', href: ordersIndex() }] },
});

const STYLE: Record<Order['status'], string> = {
    draft: 'bg-muted',
    sent: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    partial:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    received:
        'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
    cancelled:
        'bg-red-100 text-red-900 line-through dark:bg-red-900/40 dark:text-red-100',
};

const open = ref(false);
type Line = {
    stock_item_id: number | null;
    quantity: string;
    expected_rate: string;
};
const blank = (): Line => ({
    stock_item_id: null,
    quantity: '',
    expected_rate: '',
});
const form = useForm({
    supplier_id: null as number | null,
    expected_on: '',
    note: '',
    lines: [blank()] as Line[],
});
const item = (id: number | null): StockItem | undefined =>
    props.items.find((i) => i.id === id);
const total = computed(() =>
    form.lines.reduce(
        (a, l) =>
            a + Math.round(toPaisa(l.expected_rate) * Number(l.quantity || 0)),
        0,
    ),
);

function start(): void {
    form.reset();
    form.clearErrors();
    form.lines = [blank()];
    open.value = true;
}
function errorFor(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}
function save(): void {
    form.submit(PurchaseOrderController.store(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
function setStatus(o: Order, status: 'sent' | 'cancelled'): void {
    if (status === 'cancelled' && !window.confirm(`Cancel ${o.number}?`))
        return;
    router.visit(PurchaseOrderController.updateStatus(o.id), {
        data: { status },
        preserveScroll: true,
    });
}
function print(o: Order): void {
    window.open(
        PurchaseOrderController.print(o.id).url,
        '_blank',
        'width=380,height=700',
    );
}
</script>

<template>
    <Head title="Purchase orders" />
    <div class="flex flex-col gap-4 p-4">
        <StockTabs current="orders" />
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Purchase orders
                </h1>
                <p class="text-sm text-muted-foreground">
                    Receive goods from the Purchases tab by choosing the PO.
                </p>
            </div>
            <Button
                class="ml-auto h-11"
                :disabled="suppliers.length === 0 || items.length === 0"
                @click="start"
                ><Plus class="size-4" /> New PO</Button
            >
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="o in orders"
                :key="o.id"
                class="flex flex-col gap-2 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold">{{ o.number }}</div>
                        <div class="text-sm text-muted-foreground">
                            {{ o.supplier
                            }}{{
                                o.expected_on ? ` · by ${o.expected_on}` : ''
                            }}
                        </div>
                    </div>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-bold"
                        :class="STYLE[o.status]"
                        >{{ o.status_label }}</span
                    >
                </div>
                <div class="flex flex-col gap-0.5 text-sm">
                    <div
                        v-for="(l, i) in o.lines"
                        :key="i"
                        class="flex justify-between gap-2"
                    >
                        <span>{{ l.name }}</span>
                        <span class="text-muted-foreground tabular-nums"
                            >{{
                                Number(l.received_quantity)
                                    ? `${Number(l.received_quantity)} / `
                                    : ''
                            }}{{ Number(l.quantity) }} {{ l.unit }}</span
                        >
                    </div>
                </div>
                <div
                    class="flex items-center justify-between border-t pt-2 text-sm"
                >
                    <span class="text-muted-foreground">Expected</span
                    ><span class="font-bold tabular-nums">{{
                        rs(o.total)
                    }}</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold"
                        @click="print(o)"
                    >
                        <Printer class="size-3.5" /> Print
                    </button>
                    <button
                        v-if="o.status === 'draft'"
                        type="button"
                        class="inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold"
                        @click="setStatus(o, 'sent')"
                    >
                        <Send class="size-3.5" /> Mark sent
                    </button>
                    <button
                        v-if="o.status === 'draft' || o.status === 'sent'"
                        type="button"
                        class="inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold text-red-600"
                        @click="setStatus(o, 'cancelled')"
                    >
                        <X class="size-3.5" /> Cancel
                    </button>
                </div>
            </article>
            <p
                v-if="orders.length === 0"
                class="col-span-full py-10 text-center text-muted-foreground"
            >
                No purchase orders yet.
            </p>
        </div>
    </div>

    <Sheet v-model:open="open">
        <SheetContent
            class="flex w-full flex-col gap-4 overflow-y-auto sm:max-w-xl"
        >
            <SheetHeader
                ><SheetTitle>New purchase order</SheetTitle
                ><SheetDescription
                    >Quantities in the unit you buy in.</SheetDescription
                ></SheetHeader
            >
            <form class="flex flex-col gap-3 px-4 pb-6" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label>Supplier</Label>
                        <select
                            v-model="form.supplier_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option :value="null" disabled>Choose</option>
                            <option
                                v-for="s in suppliers"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.supplier_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Needed by</Label
                        ><Input v-model="form.expected_on" type="date" />
                    </div>
                </div>
                <div
                    v-for="(l, i) in form.lines"
                    :key="i"
                    class="grid grid-cols-[1fr_90px_100px_36px] items-start gap-2"
                >
                    <select
                        v-model="l.stock_item_id"
                        class="h-10 rounded-lg border bg-background px-2 text-sm"
                    >
                        <option :value="null" disabled>Item</option>
                        <option v-for="s in items" :key="s.id" :value="s.id">
                            {{ s.name }} ({{ s.purchase_unit ?? s.unit }})
                        </option>
                    </select>
                    <Input
                        v-model="l.quantity"
                        inputmode="decimal"
                        :placeholder="
                            item(l.stock_item_id)?.purchase_unit ??
                            item(l.stock_item_id)?.unit ??
                            'Qty'
                        "
                    />
                    <Input
                        v-model="l.expected_rate"
                        inputmode="decimal"
                        placeholder="Rate"
                    />
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-md border"
                        :disabled="form.lines.length === 1"
                        @click="form.lines.splice(i, 1)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                    <p
                        v-if="
                            errorFor(`lines.${i}.stock_item_id`) ||
                            errorFor(`lines.${i}.quantity`) ||
                            errorFor(`lines.${i}.expected_rate`)
                        "
                        class="col-span-4 -mt-1 text-xs text-red-600"
                    >
                        {{
                            errorFor(`lines.${i}.stock_item_id`) ||
                            errorFor(`lines.${i}.quantity`) ||
                            errorFor(`lines.${i}.expected_rate`)
                        }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 self-start"
                    @click="form.lines.push(blank())"
                    ><Plus class="size-4" /> Add line</Button
                >
                <InputError :message="form.errors.lines" />
                <Input
                    v-model="form.note"
                    placeholder="Note for supplier (optional)"
                />
                <div class="flex justify-between border-t pt-3 font-bold">
                    <span>Expected total</span
                    ><span class="tabular-nums">{{ rs(total) }}</span>
                </div>
                <Button type="submit" class="h-12" :disabled="form.processing"
                    >Create PO</Button
                >
            </form>
        </SheetContent>
    </Sheet>
</template>
