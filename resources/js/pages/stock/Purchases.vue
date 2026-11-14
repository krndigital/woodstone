<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PurchaseController from '@/actions/App/Http/Controllers/Stock/PurchaseController';
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
import { index as purchasesIndex } from '@/routes/stock/purchases';

type StockItem = {
    id: number;
    name: string;
    unit: string;
    purchase_unit: string | null;
    purchase_factor: string | null;
    avg_cost: string;
};
type PoLine = {
    id: number;
    stock_item_id: number;
    quantity: string;
    received_quantity: string;
    expected_rate: string;
};
type OpenOrder = {
    id: number;
    number: string;
    supplier_id: number;
    supplier: string;
    lines: PoLine[];
};
type Purchase = {
    id: number;
    number: string;
    supplier: string;
    supplier_bill_no: string | null;
    purchased_on: string;
    total: string;
    paid_amount: string;
    paid_method: string | null;
    by: string;
    lines: {
        name: string;
        quantity: string;
        unit: string | null;
        rate: string;
        line_total: string;
    }[];
};
type Line = {
    stock_item_id: number | null;
    quantity: string;
    in_purchase_unit: boolean;
    rate: string;
    po_line_id: number | null;
};

const props = defineProps<{
    purchases: Purchase[];
    suppliers: { id: number; name: string }[];
    items: StockItem[];
    openOrders: OpenOrder[];
    today: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stock', href: purchasesIndex() }] },
});

const open = ref(false);
const expanded = ref<number | null>(null);
const blank = (): Line => ({
    stock_item_id: null,
    quantity: '',
    in_purchase_unit: true,
    rate: '',
    po_line_id: null,
});
const form = useForm({
    supplier_id: null as number | null,
    purchase_order_id: null as number | null,
    purchased_on: props.today,
    supplier_bill_no: '',
    paid_amount: '',
    paid_method: 'cash' as 'cash' | 'qr' | 'card' | 'bank' | null,
    note: '',
    lines: [blank()] as Line[],
});
const item = (id: number | null): StockItem | undefined =>
    props.items.find((i) => i.id === id);
const ordersForSupplier = computed(() =>
    props.openOrders.filter((o) => o.supplier_id === form.supplier_id),
);

function lineTotal(l: Line): number {
    const qty = Number(l.quantity || 0);
    return Math.round(toPaisa(l.rate) * qty);
}
const total = computed(() => form.lines.reduce((a, l) => a + lineTotal(l), 0));

function start(): void {
    form.reset();
    form.clearErrors();
    form.purchased_on = props.today;
    form.lines = [blank()];
    open.value = true;
}
function usePo(id: number | null): void {
    form.purchase_order_id = id;
    const po = props.openOrders.find((o) => o.id === id);
    if (!po) {
        form.lines = [blank()];
        return;
    }
    form.lines = po.lines
        .filter((l) => Number(l.received_quantity) < Number(l.quantity))
        .map((l) => ({
            stock_item_id: l.stock_item_id,
            quantity: String(Number(l.quantity) - Number(l.received_quantity)),
            in_purchase_unit: true,
            rate: l.expected_rate,
            po_line_id: l.id,
        }));
}
function errorFor(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}
function save(): void {
    form.transform((d) => ({
        ...d,
        paid_method:
            d.paid_amount && toPaisa(d.paid_amount) > 0 ? d.paid_method : null,
    })).submit(PurchaseController.store(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Head title="Purchases" />
    <div class="flex flex-col gap-4 p-4">
        <StockTabs current="purchases" />
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Purchases</h1>
                <p class="text-sm text-muted-foreground">
                    Each purchase adds stock and updates the average cost.
                </p>
            </div>
            <Button
                class="ml-auto h-11"
                :disabled="suppliers.length === 0 || items.length === 0"
                @click="start"
                ><Plus class="size-4" /> New purchase</Button
            >
        </div>
        <p
            v-if="suppliers.length === 0 || items.length === 0"
            class="text-sm text-amber-700 dark:text-amber-400"
        >
            Add suppliers and stock items first.
        </p>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">No.</th>
                        <th class="p-3">Date</th>
                        <th class="p-3">Supplier</th>
                        <th class="p-3 text-right">Total</th>
                        <th class="p-3 text-right">Paid now</th>
                        <th class="p-3 text-right">Owed</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="p in purchases" :key="p.id">
                        <tr
                            class="cursor-pointer border-t hover:bg-muted/50"
                            @click="expanded = expanded === p.id ? null : p.id"
                        >
                            <td class="p-3 font-semibold">{{ p.number }}</td>
                            <td class="p-3">{{ p.purchased_on }}</td>
                            <td class="p-3">
                                {{ p.supplier }}
                                <div
                                    v-if="p.supplier_bill_no"
                                    class="text-xs text-muted-foreground"
                                >
                                    Bill {{ p.supplier_bill_no }}
                                </div>
                            </td>
                            <td class="p-3 text-right font-bold tabular-nums">
                                {{ rs(p.total) }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ rs(p.paid_amount) }}
                                <span class="text-xs text-muted-foreground">{{
                                    p.paid_method ?? ''
                                }}</span>
                            </td>
                            <td
                                class="p-3 text-right tabular-nums"
                                :class="
                                    toPaisa(p.total) > toPaisa(p.paid_amount)
                                        ? 'font-semibold text-amber-700 dark:text-amber-400'
                                        : ''
                                "
                            >
                                {{
                                    rs(
                                        toPaisa(p.total) -
                                            toPaisa(p.paid_amount),
                                    )
                                }}
                            </td>
                        </tr>
                        <tr v-if="expanded === p.id" class="bg-muted/30">
                            <td colspan="6" class="p-3">
                                <div
                                    v-for="(l, i) in p.lines"
                                    :key="i"
                                    class="flex justify-between py-0.5"
                                >
                                    <span
                                        >{{ l.name }} — {{ Number(l.quantity) }}
                                        {{ l.unit }} × {{ rs(l.rate) }}</span
                                    ><span class="tabular-nums">{{
                                        rs(l.line_total)
                                    }}</span>
                                </div>
                                <div class="pt-1 text-xs text-muted-foreground">
                                    Entered by {{ p.by }}
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="purchases.length === 0">
                        <td
                            colspan="6"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No purchases yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Sheet v-model:open="open">
        <SheetContent
            class="flex w-full flex-col gap-4 overflow-y-auto sm:max-w-2xl"
        >
            <SheetHeader
                ><SheetTitle>New purchase</SheetTitle
                ><SheetDescription
                    >Items come from the stock list only.</SheetDescription
                ></SheetHeader
            >
            <form class="flex flex-col gap-3 px-4 pb-6" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label>Supplier</Label>
                        <select
                            v-model="form.supplier_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            @change="usePo(null)"
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
                        <Label>Against PO (optional)</Label>
                        <select
                            :value="form.purchase_order_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            :disabled="ordersForSupplier.length === 0"
                            @change="
                                usePo(
                                    ($event.target as HTMLSelectElement).value
                                        ? Number(
                                              (
                                                  $event.target as HTMLSelectElement
                                              ).value,
                                          )
                                        : null,
                                )
                            "
                        >
                            <option value="">No PO</option>
                            <option
                                v-for="o in ordersForSupplier"
                                :key="o.id"
                                :value="o.id"
                            >
                                {{ o.number }}
                            </option>
                        </select>
                        <InputError :message="form.errors.purchase_order_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Date</Label
                        ><Input
                            v-model="form.purchased_on"
                            type="date"
                            :max="today"
                        /><InputError :message="form.errors.purchased_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Supplier bill no.</Label
                        ><Input v-model="form.supplier_bill_no" />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div
                        v-for="(l, i) in form.lines"
                        :key="i"
                        class="grid grid-cols-[1fr_90px_110px_100px_36px] items-start gap-2"
                    >
                        <select
                            v-model="l.stock_item_id"
                            class="h-10 rounded-lg border bg-background px-2 text-sm"
                            :disabled="l.po_line_id !== null"
                        >
                            <option :value="null" disabled>Item</option>
                            <option
                                v-for="s in items"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                        <Input
                            v-model="l.quantity"
                            inputmode="decimal"
                            placeholder="Qty"
                        />
                        <select
                            v-model="l.in_purchase_unit"
                            class="h-10 rounded-lg border bg-background px-2 text-sm"
                            :disabled="!item(l.stock_item_id)?.purchase_unit"
                        >
                            <option :value="true">
                                {{
                                    item(l.stock_item_id)?.purchase_unit ??
                                    item(l.stock_item_id)?.unit ??
                                    'unit'
                                }}
                            </option>
                            <option :value="false">
                                {{ item(l.stock_item_id)?.unit ?? 'unit' }}
                            </option>
                        </select>
                        <Input
                            v-model="l.rate"
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
                            class="col-span-5 -mt-1 text-xs text-muted-foreground"
                        >
                            <span class="tabular-nums">{{
                                rs(lineTotal(l))
                            }}</span>
                            <span class="text-red-600">
                                {{
                                    errorFor(`lines.${i}.stock_item_id`) ||
                                    errorFor(`lines.${i}.quantity`) ||
                                    errorFor(`lines.${i}.rate`) ||
                                    errorFor(`lines.${i}.po_line_id`) ||
                                    ''
                                }}</span
                            >
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
                </div>

                <div
                    class="flex items-center justify-between border-t pt-3 text-lg font-bold"
                >
                    <span>Total</span
                    ><span class="tabular-nums">{{ rs(total) }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label>Paid now (Rs)</Label
                        ><Input
                            v-model="form.paid_amount"
                            inputmode="decimal"
                            placeholder="0"
                        /><InputError :message="form.errors.paid_amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Paid by</Label>
                        <select
                            v-model="form.paid_method"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="cash">Cash</option>
                            <option value="qr">QR</option>
                            <option value="card">Card</option>
                            <option value="bank">Bank</option>
                        </select>
                        <InputError :message="form.errors.paid_method" />
                    </div>
                </div>
                <Input v-model="form.note" placeholder="Note (optional)" />
                <Button type="submit" class="h-12" :disabled="form.processing"
                    >Save purchase · {{ rs(total) }}</Button
                >
            </form>
        </SheetContent>
    </Sheet>
</template>
