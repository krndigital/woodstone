<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { HandCoins, Pencil, Plus, Undo2 } from '@lucide/vue';
import { ref } from 'vue';
import SupplierController from '@/actions/App/Http/Controllers/Stock/SupplierController';
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
import { index as suppliersIndex } from '@/routes/stock/suppliers';

type Supplier = {
    id: number;
    name: string;
    phone: string | null;
    pan: string | null;
    address: string | null;
    is_active: boolean;
    purchases: number;
    owed: string;
    credit_days: number;
    opening_balance: string;
    overdue: string;
    next_7: string;
};

const props = defineProps<{
    suppliers: Supplier[];
    accounts: { id: number; name: string }[];
    items: { id: number; name: string; unit: string }[];
    today: string;
    can: { pay: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stock', href: suppliersIndex() }] },
});

const open = ref(false);
const current = ref<Supplier | null>(null);
const form = useForm({
    name: '',
    phone: '',
    pan: '',
    address: '',
    is_active: true,
    credit_days: 30,
    opening_balance: '',
});
function edit(s: Supplier | null): void {
    current.value = s;
    form.clearErrors();
    form.name = s?.name ?? '';
    form.phone = s?.phone ?? '';
    form.pan = s?.pan ?? '';
    form.address = s?.address ?? '';
    form.is_active = s?.is_active ?? true;
    form.credit_days = s?.credit_days ?? 30;
    form.opening_balance = '';
    open.value = true;
}
const action = ref<'pay' | 'return' | null>(null);
const target = ref<Supplier | null>(null);
const payForm = useForm({
    amount: '',
    account_id: props.accounts[0]?.id ?? null,
    paid_on: props.today,
    reference: '',
    note: '',
});
const returnForm = useForm({
    stock_item_id: null as number | null,
    quantity: '',
    amount: '',
    reason: '',
});
function startPay(s: Supplier): void {
    target.value = s;
    payForm.reset();
    payForm.clearErrors();
    payForm.amount = s.owed;
    payForm.paid_on = props.today;
    action.value = 'pay';
}
function startReturn(s: Supplier): void {
    target.value = s;
    returnForm.reset();
    returnForm.clearErrors();
    action.value = 'return';
}
function pay(): void {
    if (target.value)
        payForm.submit(SupplierController.pay(target.value.id), {
            preserveScroll: true,
            onSuccess: () => (action.value = null),
        });
}
function returnGoods(): void {
    if (target.value)
        returnForm.submit(SupplierController.returnGoods(target.value.id), {
            preserveScroll: true,
            onSuccess: () => (action.value = null),
        });
}

function save(): void {
    form.submit(
        current.value
            ? SupplierController.update(current.value.id)
            : SupplierController.store(),
        { preserveScroll: true, onSuccess: () => (open.value = false) },
    );
}
</script>

<template>
    <Head title="Suppliers" />
    <div class="flex flex-col gap-4 p-4">
        <StockTabs current="suppliers" />
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Suppliers</h1>
                <p class="text-sm text-muted-foreground">
                    Owed = purchase amounts not paid at the time. Supplier
                    payments come with the cash book.
                </p>
            </div>
            <Button class="ml-auto h-11" @click="edit(null)"
                ><Plus class="size-4" /> Add supplier</Button
            >
        </div>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Supplier</th>
                        <th class="p-3">PAN</th>
                        <th class="p-3 text-right">Purchases</th>
                        <th class="p-3 text-right">Owed</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="s in suppliers"
                        :key="s.id"
                        class="border-t"
                        :class="s.is_active ? '' : 'opacity-50'"
                    >
                        <td class="p-3">
                            <div class="font-semibold">{{ s.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ s.phone ?? '—'
                                }}{{ s.address ? ` · ${s.address}` : '' }}
                            </div>
                        </td>
                        <td class="p-3 tabular-nums">{{ s.pan ?? '—' }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ s.purchases }}
                        </td>
                        <td
                            class="p-3 text-right font-bold tabular-nums"
                            :class="
                                toPaisa(s.owed) > 0
                                    ? 'text-amber-700 dark:text-amber-400'
                                    : ''
                            "
                        >
                            {{ rs(s.owed) }}
                            <div
                                v-if="toPaisa(s.overdue)"
                                class="text-xs font-semibold text-red-600"
                            >
                                Late {{ rs(s.overdue) }}
                            </div>
                            <div
                                v-if="toPaisa(s.next_7)"
                                class="text-xs text-amber-700"
                            >
                                Due in 7 days {{ rs(s.next_7) }}
                            </div>
                        </td>
                        <td class="p-3 text-right">
                            <button
                                v-if="can.pay"
                                type="button"
                                class="mr-1.5 inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold disabled:opacity-40"
                                :disabled="toPaisa(s.owed) <= 0"
                                @click="startPay(s)"
                            >
                                <HandCoins class="size-3.5" /> Pay
                            </button>
                            <button
                                type="button"
                                class="mr-1.5 inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold"
                                @click="startReturn(s)"
                            >
                                <Undo2 class="size-3.5" /> Return
                            </button>
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-md border"
                                aria-label="Edit"
                                @click="edit(s)"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="suppliers.length === 0">
                        <td
                            colspan="5"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No suppliers yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Sheet v-model:open="open">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <SheetHeader
                ><SheetTitle>{{
                    current ? 'Edit supplier' : 'Add supplier'
                }}</SheetTitle
                ><SheetDescription
                    >PAN is 9 digits.</SheetDescription
                ></SheetHeader
            >
            <form class="flex flex-col gap-3 px-4" @submit.prevent="save">
                <div class="grid gap-2">
                    <Label for="sn">Name</Label
                    ><Input id="sn" v-model="form.name" /><InputError
                        :message="form.errors.name"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="sp">Phone</Label
                    ><Input
                        id="sp"
                        v-model="form.phone"
                        inputmode="tel"
                    /><InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="span">PAN</Label
                    ><Input
                        id="span"
                        v-model="form.pan"
                        inputmode="numeric"
                    /><InputError :message="form.errors.pan" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="scd">Credit days</Label
                        ><Input
                            id="scd"
                            v-model.number="form.credit_days"
                            type="number"
                            min="0"
                            max="365"
                        /><InputError :message="form.errors.credit_days" />
                    </div>
                    <div v-if="!current" class="grid gap-2">
                        <Label for="sob">Opening balance we owe (Rs)</Label
                        ><Input
                            id="sob"
                            v-model="form.opening_balance"
                            inputmode="decimal"
                            placeholder="0"
                        /><InputError :message="form.errors.opening_balance" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="sa">Address</Label
                    ><Input id="sa" v-model="form.address" />
                </div>
                <label v-if="current" class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4"
                    />
                    Active</label
                >
                <Button type="submit" class="h-12" :disabled="form.processing"
                    >Save</Button
                >
            </form>
        </SheetContent>
    </Sheet>

    <Sheet :open="action !== null" @update:open="(v) => !v && (action = null)">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <template v-if="action === 'pay' && target">
                <SheetHeader
                    ><SheetTitle>Pay {{ target.name }}</SheetTitle
                    ><SheetDescription
                        >We owe {{ rs(target.owed) }}</SheetDescription
                    ></SheetHeader
                >
                <form class="flex flex-col gap-3 px-4" @submit.prevent="pay">
                    <div class="grid gap-2">
                        <Label>Amount (Rs)</Label
                        ><Input
                            v-model="payForm.amount"
                            inputmode="decimal"
                        /><InputError :message="payForm.errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Paid from</Label
                        ><select
                            v-model="payForm.account_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option
                                v-for="a in accounts"
                                :key="a.id"
                                :value="a.id"
                            >
                                {{ a.name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Date</Label
                        ><Input
                            v-model="payForm.paid_on"
                            type="date"
                            :max="today"
                        /><InputError :message="payForm.errors.paid_on" />
                    </div>
                    <Input
                        v-model="payForm.reference"
                        placeholder="Cheque / transaction no. (optional)"
                    />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="payForm.processing"
                        >Save payment</Button
                    >
                </form>
            </template>
            <template v-else-if="action === 'return' && target">
                <SheetHeader
                    ><SheetTitle>Return goods to {{ target.name }}</SheetTitle
                    ><SheetDescription
                        >Stock goes down and we owe less.</SheetDescription
                    ></SheetHeader
                >
                <form
                    class="flex flex-col gap-3 px-4"
                    @submit.prevent="returnGoods"
                >
                    <select
                        v-model="returnForm.stock_item_id"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option :value="null" disabled>Item</option>
                        <option v-for="i in items" :key="i.id" :value="i.id">
                            {{ i.name }} ({{ i.unit }})
                        </option>
                    </select>
                    <InputError :message="returnForm.errors.stock_item_id" />
                    <div class="grid grid-cols-2 gap-2">
                        <Input
                            v-model="returnForm.quantity"
                            inputmode="decimal"
                            placeholder="Quantity (base unit)"
                        />
                        <Input
                            v-model="returnForm.amount"
                            inputmode="decimal"
                            placeholder="Credit (Rs)"
                        />
                    </div>
                    <InputError
                        :message="
                            returnForm.errors.quantity ||
                            returnForm.errors.amount
                        "
                    />
                    <Input
                        v-model="returnForm.reason"
                        placeholder="Reason (damaged, expired…)"
                    />
                    <InputError :message="returnForm.errors.reason" />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="returnForm.processing"
                        >Save return</Button
                    >
                </form>
            </template>
        </SheetContent>
    </Sheet>
</template>
