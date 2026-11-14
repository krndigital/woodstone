<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRightLeft, HandCoins, Pencil, Plus } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/Dues/CustomerController';
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
import { rs, toPaisa } from '@/lib/money';
import { index as customersIndex } from '@/routes/customers';

type Customer = {
    id: number;
    name: string;
    phone: string | null;
    group_id: number | null;
    group: string | null;
    note: string | null;
    credit_limit: string;
    is_active: boolean;
    balance: string;
    age_0_30: string;
    age_31_60: string;
    age_60_plus: string;
    oldest: string | null;
};

const props = defineProps<{
    customers: Customer[];
    totals: {
        balance: string;
        age_0_30: string;
        age_31_60: string;
        age_60_plus: string;
    };
    groups: { id: number; name: string }[];
    methods: { value: 'cash' | 'qr' | 'card'; label: string }[];
    defaultLimit: string;
    can: { approve: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers & dues', href: customersIndex() }],
    },
});

const q = ref('');
const onlyDue = ref(false);
const group = ref<number | 'all'>('all');
const shown = computed(() => {
    const s = q.value.trim().toLowerCase();
    return props.customers.filter(
        (c) =>
            (s === '' ||
                c.name.toLowerCase().includes(s) ||
                (c.phone ?? '').includes(s)) &&
            (!onlyDue.value || toPaisa(c.balance) > 0) &&
            (group.value === 'all' || c.group_id === group.value),
    );
});

/* panel: edit / receive / move */
const panel = ref<'edit' | 'receive' | 'move' | null>(null);
const current = ref<Customer | null>(null);

const editForm = useForm({
    name: '',
    phone: '',
    customer_group_id: null as number | null,
    note: '',
    credit_limit: '',
    is_active: true,
});
function openEdit(c: Customer | null): void {
    current.value = c;
    editForm.clearErrors();
    editForm.name = c?.name ?? '';
    editForm.phone = c?.phone ?? '';
    editForm.customer_group_id = c?.group_id ?? null;
    editForm.note = c?.note ?? '';
    editForm.credit_limit = c?.credit_limit ?? props.defaultLimit;
    editForm.is_active = c?.is_active ?? true;
    panel.value = 'edit';
}
function saveCustomer(): void {
    editForm.submit(
        current.value
            ? CustomerController.update(current.value.id)
            : CustomerController.store(),
        {
            preserveScroll: true,
            onSuccess: () => (panel.value = null),
        },
    );
}

const payForm = useForm({
    method: 'cash' as 'cash' | 'qr' | 'card',
    amount: '',
    reference: '',
    qr_checked: false,
    note: '',
});
function openReceive(c: Customer): void {
    current.value = c;
    payForm.reset();
    payForm.clearErrors();
    payForm.amount = c.balance;
    panel.value = 'receive';
}
function receive(): void {
    if (!current.value) return;
    payForm.submit(CustomerController.receive(current.value.id), {
        preserveScroll: true,
        onSuccess: () => (panel.value = null),
    });
}

const moveForm = useForm({
    to_customer_id: null as number | null,
    amount: '',
    note: '',
    approver_username: '',
    approver_secret: '',
});
function openMove(c: Customer): void {
    current.value = c;
    moveForm.reset();
    moveForm.clearErrors();
    moveForm.amount = c.balance;
    panel.value = 'move';
}
function move(): void {
    if (!current.value) return;
    moveForm.submit(CustomerController.move(current.value.id), {
        preserveScroll: true,
        onSuccess: () => (panel.value = null),
        onFinish: () => (moveForm.approver_secret = ''),
    });
}

// After receiving a due payment the server asks for the receipt to be printed (FR-15.6).
let off: (() => void) | undefined;
onMounted(() => {
    off = router.on('flash', (event) => {
        const id = (event as CustomEvent).detail?.flash?.printReceipt as
            | number
            | undefined;
        if (id)
            window.open(
                CustomerController.receipt(id).url,
                '_blank',
                'width=380,height=700',
            );
    });
});
onBeforeUnmount(() => off?.());

const groupForm = useForm({ name: '' });
function addGroup(): void {
    groupForm.submit(CustomerController.storeGroup(), {
        preserveScroll: true,
        onSuccess: () => groupForm.reset(),
    });
}
</script>

<template>
    <Head title="Customers & dues" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Customers & dues
                </h1>
                <p class="text-sm text-muted-foreground">
                    Total due {{ rs(totals.balance) }}
                </p>
            </div>
            <Button class="ml-auto h-11" @click="openEdit(null)"
                ><Plus class="size-4" /> Add customer</Button
            >
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">Total due</div>
                <div class="text-xl font-bold tabular-nums">
                    {{ rs(totals.balance) }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">0–30 days</div>
                <div class="text-xl font-bold tabular-nums">
                    {{ rs(totals.age_0_30) }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">31–60 days</div>
                <div
                    class="text-xl font-bold text-amber-700 tabular-nums dark:text-amber-400"
                >
                    {{ rs(totals.age_31_60) }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">60+ days</div>
                <div class="text-xl font-bold text-red-600 tabular-nums">
                    {{ rs(totals.age_60_plus) }}
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Input
                v-model="q"
                class="h-10 w-64"
                placeholder="Search name or phone"
            />
            <label
                class="flex h-10 items-center gap-2 rounded-lg border px-3 text-sm"
                ><input v-model="onlyDue" type="checkbox" class="size-4" /> Only
                with due</label
            >
            <select
                v-model="group"
                class="h-10 rounded-lg border bg-background px-3 text-sm"
            >
                <option value="all">All groups</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">
                    {{ g.name }}
                </option>
            </select>
            <form
                class="ml-auto flex items-center gap-2"
                @submit.prevent="addGroup"
            >
                <Input
                    v-model="groupForm.name"
                    class="h-10 w-40"
                    placeholder="New group"
                />
                <Button
                    type="submit"
                    variant="outline"
                    class="h-10"
                    :disabled="groupForm.processing || !groupForm.name"
                    >Add group</Button
                >
            </form>
        </div>
        <InputError :message="groupForm.errors.name" />

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Customer</th>
                        <th class="p-3">Group</th>
                        <th class="p-3 text-right">Due</th>
                        <th class="p-3 text-right">Limit</th>
                        <th class="p-3 text-right">31–60</th>
                        <th class="p-3 text-right">60+</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in shown"
                        :key="c.id"
                        class="border-t"
                        :class="c.is_active ? '' : 'opacity-50'"
                    >
                        <td class="p-3">
                            <Link
                                :href="CustomerController.show(c.id).url"
                                class="font-semibold hover:underline"
                                >{{ c.name }}</Link
                            >
                            <div class="text-xs text-muted-foreground">
                                {{ c.phone ?? '—'
                                }}{{ c.is_active ? '' : ' · turned off' }}
                            </div>
                        </td>
                        <td class="p-3">{{ c.group ?? '—' }}</td>
                        <td
                            class="p-3 text-right font-bold tabular-nums"
                            :class="
                                toPaisa(c.balance) > toPaisa(c.credit_limit)
                                    ? 'text-red-600'
                                    : ''
                            "
                        >
                            {{ rs(c.balance) }}
                        </td>
                        <td
                            class="p-3 text-right text-muted-foreground tabular-nums"
                        >
                            {{ rs(c.credit_limit) }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ toPaisa(c.age_31_60) ? rs(c.age_31_60) : '—' }}
                        </td>
                        <td
                            class="p-3 text-right tabular-nums"
                            :class="
                                toPaisa(c.age_60_plus)
                                    ? 'font-semibold text-red-600'
                                    : ''
                            "
                        >
                            {{
                                toPaisa(c.age_60_plus) ? rs(c.age_60_plus) : '—'
                            }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1.5">
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold disabled:opacity-40"
                                    :disabled="toPaisa(c.balance) <= 0"
                                    @click="openReceive(c)"
                                >
                                    <HandCoins class="size-3.5" /> Receive
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold disabled:opacity-40"
                                    :disabled="toPaisa(c.balance) <= 0"
                                    @click="openMove(c)"
                                >
                                    <ArrowRightLeft class="size-3.5" /> Move
                                </button>
                                <button
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-md border"
                                    aria-label="Edit"
                                    @click="openEdit(c)"
                                >
                                    <Pencil class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="shown.length === 0">
                        <td
                            colspan="7"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No customers.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Sheet :open="panel !== null" @update:open="(v) => !v && (panel = null)">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <template v-if="panel === 'edit'">
                <SheetHeader
                    ><SheetTitle>{{
                        current ? 'Edit customer' : 'Add customer'
                    }}</SheetTitle
                    ><SheetDescription
                        >Find customers by phone at billing.</SheetDescription
                    ></SheetHeader
                >
                <form
                    class="flex flex-col gap-3 px-4"
                    @submit.prevent="saveCustomer"
                >
                    <div class="grid gap-2">
                        <Label for="n">Name</Label
                        ><Input id="n" v-model="editForm.name" /><InputError
                            :message="editForm.errors.name"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="p">Phone</Label
                        ><Input
                            id="p"
                            v-model="editForm.phone"
                            inputmode="tel"
                        /><InputError :message="editForm.errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="g">Group</Label>
                        <select
                            id="g"
                            v-model="editForm.customer_group_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option :value="null">No group</option>
                            <option
                                v-for="g in groups"
                                :key="g.id"
                                :value="g.id"
                            >
                                {{ g.name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="l">Due limit (Rs)</Label>
                        <Input
                            id="l"
                            v-model="editForm.credit_limit"
                            inputmode="decimal"
                            :disabled="!can.approve"
                        />
                        <p
                            v-if="!can.approve"
                            class="text-xs text-muted-foreground"
                        >
                            Only a manager can change the limit.
                        </p>
                        <InputError :message="editForm.errors.credit_limit" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="no">Note</Label
                        ><Input id="no" v-model="editForm.note" />
                    </div>
                    <label
                        v-if="current && can.approve"
                        class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="editForm.is_active"
                            type="checkbox"
                            class="size-4"
                        />
                        Account is on</label
                    >
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="editForm.processing"
                        >Save</Button
                    >
                </form>
            </template>

            <template v-else-if="panel === 'receive' && current">
                <SheetHeader
                    ><SheetTitle>Receive from {{ current.name }}</SheetTitle
                    ><SheetDescription
                        >Due now {{ rs(current.balance) }}</SheetDescription
                    ></SheetHeader
                >
                <form
                    class="flex flex-col gap-3 px-4"
                    @submit.prevent="receive"
                >
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="m in methods"
                            :key="m.value"
                            type="button"
                            class="h-12 rounded-lg border font-semibold"
                            :class="
                                payForm.method === m.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : ''
                            "
                            @click="payForm.method = m.value"
                        >
                            {{ m.label }}
                        </button>
                    </div>
                    <div class="grid gap-2">
                        <Label for="a">Amount (Rs)</Label
                        ><Input
                            id="a"
                            v-model="payForm.amount"
                            inputmode="decimal"
                        /><InputError
                            :message="
                                payForm.errors.amount || payForm.errors.method
                            "
                        />
                    </div>
                    <label
                        v-if="payForm.method === 'qr'"
                        class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        <input
                            v-model="payForm.qr_checked"
                            type="checkbox"
                            class="size-5"
                        />
                        I have checked the payment in the app
                    </label>
                    <InputError :message="payForm.errors.qr_checked" />
                    <Input
                        v-if="payForm.method !== 'cash'"
                        v-model="payForm.reference"
                        placeholder="Transaction ID (optional)"
                    />
                    <Input
                        v-model="payForm.note"
                        placeholder="Note (optional)"
                    />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="payForm.processing"
                        >Receive
                        {{ payForm.amount ? rs(payForm.amount) : '' }}</Button
                    >
                </form>
            </template>

            <template v-else-if="panel === 'move' && current">
                <SheetHeader
                    ><SheetTitle>Move due from {{ current.name }}</SheetTitle
                    ><SheetDescription
                        >For example to the office or friend who will pay. Needs
                        manager approval.</SheetDescription
                    ></SheetHeader
                >
                <form class="flex flex-col gap-3 px-4" @submit.prevent="move">
                    <div class="grid gap-2">
                        <Label for="to">Move to</Label>
                        <select
                            id="to"
                            v-model="moveForm.to_customer_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option :value="null" disabled>
                                Choose customer
                            </option>
                            <option
                                v-for="c in customers.filter(
                                    (x) => x.id !== current?.id && x.is_active,
                                )"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }} {{ c.phone ?? '' }}
                            </option>
                        </select>
                        <InputError :message="moveForm.errors.to_customer_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ma">Amount (Rs)</Label
                        ><Input
                            id="ma"
                            v-model="moveForm.amount"
                            inputmode="decimal"
                        /><InputError :message="moveForm.errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="mn">Reason</Label
                        ><Input id="mn" v-model="moveForm.note" /><InputError
                            :message="moveForm.errors.note"
                        />
                    </div>
                    <template v-if="!can.approve">
                        <Input
                            v-model="moveForm.approver_username"
                            placeholder="Manager username"
                            autocomplete="off"
                        />
                        <Input
                            v-model="moveForm.approver_secret"
                            type="password"
                            placeholder="Manager PIN or password"
                            autocomplete="off"
                        />
                    </template>
                    <InputError :message="moveForm.errors.approver_username" />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="moveForm.processing"
                        >Move due</Button
                    >
                </form>
            </template>
        </SheetContent>
    </Sheet>
</template>
