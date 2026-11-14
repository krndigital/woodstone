<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Banknote,
    CreditCard,
    Minus,
    Plus,
    QrCode,
    UserRound,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import BillController from '@/actions/App/Http/Controllers/Billing/BillController';
import PosController from '@/actions/App/Http/Controllers/Pos/PosController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computeBill, paisaToInput, splitByWeights } from '@/lib/bill';
import { rs, toPaisa } from '@/lib/money';
import { index as posIndex } from '@/routes/pos';

type Method = 'cash' | 'qr' | 'card' | 'due';
type Line = {
    id: number;
    name: string;
    quantity: number;
    unit_price: string;
    line_total: string;
    status: string;
    modifiers: { name: string }[];
};
type Customer = {
    id: number;
    name: string;
    phone: string | null;
    balance: string;
    credit_limit: string;
};
type Person = {
    payer: string;
    method: Method;
    amount: string;
    customer_id: number | null;
    reference: string;
    qr_checked: boolean;
};

const props = defineProps<{
    order: {
        id: number;
        number: string;
        label: string;
        subtotal: string;
        guests: number;
        items: Line[];
    };
    vatable: string;
    settings: {
        service_percent: string;
        vat_percent: string;
        discount_limit: number;
        methods: { value: Method; label: string }[];
        auto_print: boolean;
    };
    customers: Customer[];
    can: { discount: boolean; approve: boolean; due: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'POS', href: posIndex() }] },
});

const ICON: Record<Method, typeof Banknote> = {
    cash: Banknote,
    qr: QrCode,
    card: CreditCard,
    due: UserRound,
};
const lines = computed(() =>
    props.order.items.filter((l) => l.status !== 'void'),
);
const methods = computed(() =>
    props.settings.methods.filter((m) => m.value !== 'due' || props.can.due),
);

/* discount */
const discountType = ref<'percent' | 'amount' | null>(null);
const discountValue = ref('');
const discountReason = ref('');
const totals = computed(() =>
    computeBill(
        toPaisa(props.order.subtotal),
        toPaisa(props.vatable),
        discountType.value,
        discountValue.value,
        props.settings.service_percent,
        props.settings.vat_percent,
    ),
);
function setDiscountType(t: 'percent' | 'amount' | null): void {
    discountType.value = t;
    if (t === null) discountValue.value = '';
}
const overLimit = computed(
    () =>
        totals.value.discount > 0 &&
        totals.value.discountBp > props.settings.discount_limit * 100,
);

/* payers */
type Mode = 'single' | 'amount' | 'items';
const mode = ref<Mode>('single');
const people = ref<Person[]>([]);
const newPerson = (i: number): Person => ({
    payer: `Person ${i + 1}`,
    method: 'cash',
    amount: '0.00',
    customer_id: null,
    reference: '',
    qr_checked: false,
});
/** item split: per line, how many units each person takes */
const itemShare = reactive<Record<number, number[]>>({});

function setPeople(n: number): void {
    const next = Array.from(
        { length: n },
        (_, i) => people.value[i] ?? newPerson(i),
    );
    people.value = next;
    lines.value.forEach((l) => {
        const cur = itemShare[l.id] ?? [];
        itemShare[l.id] = Array.from({ length: n }, (_, i) => cur[i] ?? 0);
    });
    recalcAmounts();
}

function recalcAmounts(): void {
    const total = totals.value.total;
    if (mode.value === 'single') {
        if (people.value.length !== 1)
            people.value = [people.value[0] ?? newPerson(0)];
        people.value[0].payer = '';
        people.value[0].amount = paisaToInput(total);
        return;
    }
    if (mode.value === 'amount') {
        const shares = splitByWeights(
            total,
            people.value.map(() => 1),
        );
        people.value.forEach((p, i) => (p.amount = paisaToInput(shares[i])));
        return;
    }
    // by items: weight = value of units each person took; unassigned value is shared equally
    const weights = people.value.map(() => 0);
    let unassigned = 0;
    lines.value.forEach((l) => {
        const unit = Math.round(toPaisa(l.line_total) / l.quantity);
        const shares = itemShare[l.id] ?? [];
        let taken = 0;
        shares.forEach((q, i) => {
            weights[i] += q * unit;
            taken += q;
        });
        unassigned += Math.max(0, l.quantity - taken) * unit;
    });
    const each = weights.map(
        (w) => w + unassigned / Math.max(1, weights.length),
    );
    const shares = splitByWeights(total, each);
    people.value.forEach((p, i) => (p.amount = paisaToInput(shares[i])));
}

function chooseMode(m: Mode): void {
    mode.value = m;
    if (m === 'single') setPeople(1);
    else
        setPeople(
            Math.max(
                2,
                people.value.length > 1
                    ? people.value.length
                    : Math.min(props.order.guests, 10) || 2,
            ),
        );
}
function unitsLeft(line: Line): number {
    return (
        line.quantity - (itemShare[line.id] ?? []).reduce((a, b) => a + b, 0)
    );
}
function take(line: Line, person: number, delta: number): void {
    const arr = itemShare[line.id];
    if (!arr) return;
    if (delta > 0 && unitsLeft(line) <= 0) return;
    arr[person] = Math.max(0, (arr[person] ?? 0) + delta);
    recalcAmounts();
}

setPeople(1);
watch(() => totals.value.total, recalcAmounts);

const paidSum = computed(() =>
    people.value.reduce((a, p) => a + toPaisa(p.amount), 0),
);
const difference = computed(() => totals.value.total - paidSum.value);

/* cash change helper (single payment) */
const tendered = ref('');
const change = computed(() =>
    Math.max(0, toPaisa(tendered.value) - totals.value.total),
);

function customerName(id: number | null): string {
    return props.customers.find((c) => c.id === id)?.name ?? '';
}
const customerQuery = ref<Record<number, string>>({});
function customerMatches(i: number): Customer[] {
    const q = (customerQuery.value[i] ?? '').trim().toLowerCase();
    if (q === '') return props.customers.slice(0, 6);
    return props.customers
        .filter(
            (c) =>
                c.name.toLowerCase().includes(q) || (c.phone ?? '').includes(q),
        )
        .slice(0, 6);
}
function overCredit(p: Person): boolean {
    const c = props.customers.find((x) => x.id === p.customer_id);
    return (
        c !== undefined &&
        toPaisa(c.balance) + toPaisa(p.amount) > toPaisa(c.credit_limit)
    );
}
const needsApproval = computed(
    () =>
        (totals.value.discount > 0 &&
            (!props.can.discount || overLimit.value)) ||
        people.value.some((p) => p.method === 'due' && overCredit(p)),
);

/* submit */
const form = useForm({
    discount_type: null as 'percent' | 'amount' | null,
    discount_value: '',
    discount_reason: '',
    payments: [] as {
        payer: string | null;
        method: Method;
        amount: string;
        customer_id: number | null;
        reference: string | null;
        qr_checked: boolean;
    }[],
    approver_username: '',
    approver_secret: '',
});
function errorFor(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}
function pay(): void {
    form.discount_type = totals.value.discount > 0 ? discountType.value : null;
    form.discount_value = totals.value.discount > 0 ? discountValue.value : '';
    form.discount_reason = discountReason.value;
    form.payments =
        totals.value.total === 0
            ? []
            : people.value
                  .filter((p) => toPaisa(p.amount) > 0)
                  .map((p) => ({
                      payer: p.payer || null,
                      method: p.method,
                      amount: p.amount,
                      customer_id: p.method === 'due' ? p.customer_id : null,
                      reference: p.reference || null,
                      qr_checked: p.method === 'qr' ? p.qr_checked : false,
                  }));
    form.submit(BillController.settle(props.order.id), {
        preserveScroll: true,
        onFinish: () => (form.approver_secret = ''),
    });
}
</script>

<template>
    <Head :title="`Bill · ${order.label}`" />

    <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_420px]">
        <section class="flex min-w-0 flex-col gap-4">
            <div class="flex items-center gap-2">
                <Link
                    :href="PosController.show(order.id).url"
                    class="flex size-11 items-center justify-center rounded-lg border"
                    aria-label="Back to order"
                    ><ArrowLeft class="size-5"
                /></Link>
                <div>
                    <h1 class="text-xl font-bold">Bill · {{ order.label }}</h1>
                    <p class="text-xs text-muted-foreground">
                        Order #{{ order.number }} · {{ order.guests }} guests
                    </p>
                </div>
            </div>

            <!-- how to pay -->
            <div class="flex rounded-lg bg-muted p-1">
                <button
                    v-for="m in [
                        ['single', 'One payment'],
                        ['amount', 'Split by amount'],
                        ['items', 'Split by items'],
                    ] as const"
                    :key="m[0]"
                    type="button"
                    class="h-11 flex-1 rounded-md text-sm font-semibold"
                    :class="
                        mode === m[0]
                            ? 'bg-background shadow-sm'
                            : 'text-muted-foreground'
                    "
                    @click="chooseMode(m[0])"
                >
                    {{ m[1] }}
                </button>
            </div>

            <div v-if="mode !== 'single'" class="flex items-center gap-3">
                <Label>People</Label>
                <button
                    type="button"
                    class="flex size-10 items-center justify-center rounded-md border"
                    :disabled="people.length <= 2"
                    @click="setPeople(people.length - 1)"
                >
                    <Minus class="size-4" />
                </button>
                <span class="w-6 text-center text-lg font-bold">{{
                    people.length
                }}</span>
                <button
                    type="button"
                    class="flex size-10 items-center justify-center rounded-md border"
                    :disabled="people.length >= 10"
                    @click="setPeople(people.length + 1)"
                >
                    <Plus class="size-4" />
                </button>
                <span class="text-sm text-muted-foreground">{{
                    mode === 'amount'
                        ? 'Equal shares; you can change any amount.'
                        : 'Tap + under a person to give them items. Items nobody takes are shared.'
                }}</span>
            </div>

            <!-- split by items grid -->
            <div
                v-if="mode === 'items'"
                class="overflow-x-auto rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead class="bg-muted text-left">
                        <tr>
                            <th class="p-2">Item</th>
                            <th
                                v-for="(p, i) in people"
                                :key="i"
                                class="p-2 text-center"
                            >
                                {{ p.payer }}
                            </th>
                            <th class="p-2 text-center">Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in lines" :key="l.id" class="border-t">
                            <td class="p-2">
                                <div class="font-semibold">{{ l.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ l.quantity }} × {{ rs(l.unit_price) }}
                                </div>
                            </td>
                            <td v-for="(p, i) in people" :key="i" class="p-2">
                                <div
                                    class="flex items-center justify-center gap-1"
                                >
                                    <button
                                        type="button"
                                        class="flex size-8 items-center justify-center rounded border"
                                        @click="take(l, i, -1)"
                                    >
                                        <Minus class="size-3" />
                                    </button>
                                    <span
                                        class="w-5 text-center font-bold tabular-nums"
                                        >{{ itemShare[l.id]?.[i] ?? 0 }}</span
                                    >
                                    <button
                                        type="button"
                                        class="flex size-8 items-center justify-center rounded border"
                                        :disabled="unitsLeft(l) <= 0"
                                        @click="take(l, i, 1)"
                                    >
                                        <Plus class="size-3" />
                                    </button>
                                </div>
                            </td>
                            <td
                                class="p-2 text-center tabular-nums"
                                :class="
                                    unitsLeft(l) > 0
                                        ? 'font-bold text-amber-600'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ unitsLeft(l) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- payers -->
            <div
                class="grid gap-3"
                :class="mode === 'single' ? '' : 'sm:grid-cols-2'"
            >
                <div
                    v-for="(p, i) in people"
                    :key="i"
                    class="flex flex-col gap-3 rounded-xl border p-4"
                >
                    <div class="flex items-center gap-2">
                        <Input
                            v-if="mode !== 'single'"
                            v-model="p.payer"
                            class="h-9 w-32 font-semibold"
                        />
                        <span v-else class="font-semibold">Payment</span>
                        <div class="ml-auto flex items-center gap-1">
                            <span class="text-sm text-muted-foreground"
                                >Rs</span
                            >
                            <Input
                                v-model="p.amount"
                                class="h-10 w-28 text-right font-bold tabular-nums"
                                inputmode="decimal"
                                :readonly="mode === 'single'"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button
                            v-for="m in methods"
                            :key="m.value"
                            type="button"
                            class="flex h-14 flex-col items-center justify-center gap-0.5 rounded-lg border text-xs font-semibold"
                            :class="
                                p.method === m.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : ''
                            "
                            @click="p.method = m.value"
                        >
                            <component :is="ICON[m.value]" class="size-4" />
                            {{ m.label }}
                        </button>
                    </div>
                    <InputError
                        :message="
                            errorFor(`payments.${i}.method`) ||
                            errorFor(`payments.${i}.amount`)
                        "
                    />

                    <template v-if="p.method === 'cash' && mode === 'single'">
                        <div class="flex items-center gap-2">
                            <Label class="w-24">Cash given</Label>
                            <Input
                                v-model="tendered"
                                class="h-10"
                                inputmode="decimal"
                                placeholder="0"
                            />
                        </div>
                        <p
                            v-if="change > 0"
                            class="text-lg font-bold text-green-700 dark:text-green-400"
                        >
                            Return {{ rs(change) }}
                        </p>
                    </template>

                    <template v-if="p.method === 'qr'">
                        <label
                            class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                        >
                            <input
                                v-model="p.qr_checked"
                                type="checkbox"
                                class="size-5"
                            />
                            I have checked the payment in the Fonepay / eSewa
                            app
                        </label>
                        <Input
                            v-model="p.reference"
                            placeholder="Transaction ID (optional)"
                        />
                        <InputError
                            :message="errorFor(`payments.${i}.qr_checked`)"
                        />
                    </template>
                    <Input
                        v-if="p.method === 'card'"
                        v-model="p.reference"
                        placeholder="Card slip no. (optional)"
                    />

                    <template v-if="p.method === 'due'">
                        <div
                            v-if="p.customer_id"
                            class="flex items-center justify-between rounded-lg border p-2 text-sm"
                        >
                            <span
                                ><b>{{ customerName(p.customer_id) }}</b></span
                            >
                            <button
                                type="button"
                                class="text-xs underline"
                                @click="p.customer_id = null"
                            >
                                Change
                            </button>
                        </div>
                        <template v-else>
                            <Input
                                v-model="customerQuery[i]"
                                placeholder="Search customer name or phone"
                            />
                            <div class="flex flex-col gap-1">
                                <button
                                    v-for="c in customerMatches(i)"
                                    :key="c.id"
                                    type="button"
                                    class="flex justify-between rounded-md border px-3 py-2 text-left text-sm hover:bg-muted"
                                    @click="p.customer_id = c.id"
                                >
                                    <span
                                        >{{ c.name }}
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{ c.phone }}</span
                                        ></span
                                    >
                                    <span class="text-xs tabular-nums"
                                        >Due {{ rs(c.balance) }}</span
                                    >
                                </button>
                                <p
                                    v-if="customers.length === 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    No customers yet. Add them on the Customers
                                    page.
                                </p>
                            </div>
                        </template>
                        <p
                            v-if="overCredit(p)"
                            class="text-sm font-semibold text-red-600"
                        >
                            Over this customer's due limit — needs manager
                            approval.
                        </p>
                        <InputError
                            :message="errorFor(`payments.${i}.customer_id`)"
                        />
                    </template>
                </div>
            </div>
        </section>

        <!-- totals -->
        <aside
            class="flex flex-col gap-3 rounded-xl border p-4 lg:sticky lg:top-4 lg:self-start"
        >
            <div class="flex max-h-56 flex-col gap-1 overflow-y-auto text-sm">
                <div
                    v-for="l in lines"
                    :key="l.id"
                    class="flex justify-between gap-2"
                >
                    <span class="truncate">{{ l.quantity }} × {{ l.name }}</span
                    ><span class="tabular-nums">{{ rs(l.line_total) }}</span>
                </div>
            </div>

            <div
                v-if="can.discount || can.approve"
                class="flex flex-col gap-2 border-t pt-3"
            >
                <div class="flex items-center gap-2">
                    <Label class="mr-auto">Discount</Label>
                    <div class="flex rounded-md bg-muted p-0.5 text-sm">
                        <button
                            v-for="t in [
                                [null, 'None'],
                                ['percent', '%'],
                                ['amount', 'Rs'],
                            ] as const"
                            :key="String(t[0])"
                            type="button"
                            class="h-8 rounded px-3 font-semibold"
                            :class="
                                discountType === t[0]
                                    ? 'bg-background shadow-sm'
                                    : 'text-muted-foreground'
                            "
                            @click="setDiscountType(t[0])"
                        >
                            {{ t[1] }}
                        </button>
                    </div>
                </div>
                <template v-if="discountType">
                    <div class="flex gap-1.5">
                        <Input
                            v-model="discountValue"
                            class="h-10"
                            inputmode="decimal"
                            :placeholder="
                                discountType === 'percent'
                                    ? 'e.g. 10'
                                    : 'e.g. 100'
                            "
                        />
                        <template v-if="discountType === 'percent'">
                            <button
                                v-for="v in ['5', '10', '15']"
                                :key="v"
                                type="button"
                                class="h-10 rounded-md border px-3 text-sm font-semibold"
                                @click="discountValue = v"
                            >
                                {{ v }}%
                            </button>
                        </template>
                    </div>
                    <InputError :message="form.errors.discount_value" />
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="r in [
                                'Regular customer',
                                'Staff',
                                'Owner guest',
                                'Complaint',
                            ]"
                            :key="r"
                            type="button"
                            class="h-8 rounded-full border px-3 text-xs"
                            :class="
                                discountReason === r
                                    ? 'border-foreground bg-foreground text-background'
                                    : ''
                            "
                            @click="discountReason = r"
                        >
                            {{ r }}
                        </button>
                    </div>
                    <Input v-model="discountReason" placeholder="Reason" />
                    <InputError :message="form.errors.discount_reason" />
                    <p
                        v-if="overLimit"
                        class="text-sm font-semibold text-amber-700 dark:text-amber-400"
                    >
                        Above {{ settings.discount_limit }}% — needs manager
                        approval.
                    </p>
                </template>
            </div>

            <div class="flex flex-col gap-1 border-t pt-3 text-sm">
                <div class="flex justify-between">
                    <span>Subtotal</span
                    ><span class="tabular-nums">{{ rs(totals.subtotal) }}</span>
                </div>
                <div
                    v-if="totals.discount"
                    class="flex justify-between text-green-700 dark:text-green-400"
                >
                    <span>Discount</span
                    ><span class="tabular-nums"
                        >− {{ rs(totals.discount) }}</span
                    >
                </div>
                <div class="flex justify-between text-muted-foreground">
                    <span>Service charge ({{ settings.service_percent }}%)</span
                    ><span class="tabular-nums">{{ rs(totals.service) }}</span>
                </div>
                <div class="flex justify-between text-muted-foreground">
                    <span>VAT ({{ settings.vat_percent }}%)</span
                    ><span class="tabular-nums">{{ rs(totals.vat) }}</span>
                </div>
                <div class="flex justify-between pt-1 text-2xl font-bold">
                    <span>Total</span
                    ><span class="tabular-nums">{{ rs(totals.total) }}</span>
                </div>
                <div
                    v-if="difference !== 0"
                    class="flex justify-between font-semibold text-red-600"
                >
                    <span>{{
                        difference > 0 ? 'Still to pay' : 'Paying too much'
                    }}</span
                    ><span class="tabular-nums">{{
                        rs(Math.abs(difference))
                    }}</span>
                </div>
            </div>

            <template v-if="needsApproval && !can.approve">
                <p class="text-sm font-semibold">Manager approval</p>
                <Input
                    v-model="form.approver_username"
                    placeholder="Manager username"
                    autocomplete="off"
                />
                <Input
                    v-model="form.approver_secret"
                    type="password"
                    placeholder="Manager PIN or password"
                    autocomplete="off"
                />
            </template>
            <InputError
                :message="
                    form.errors.approver_username ||
                    form.errors.payments ||
                    errorFor('order')
                "
            />

            <Button
                class="h-14 text-base"
                :disabled="form.processing || difference !== 0"
                @click="pay"
                >Take payment · {{ rs(totals.total) }}</Button
            >
            <p class="text-center text-xs text-muted-foreground">
                {{
                    settings.auto_print
                        ? 'The bill prints after payment.'
                        : 'Print the bill from Bills after payment.'
                }}
            </p>
        </aside>
    </div>
</template>
