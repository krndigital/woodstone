<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MessageCircle, Printer } from '@lucide/vue';
import CustomerController from '@/actions/App/Http/Controllers/Dues/CustomerController';
import BillController from '@/actions/App/Http/Controllers/Billing/BillController';
import { usePermissions } from '@/composables/usePermissions';
import { rs, toPaisa } from '@/lib/money';
import { index as customersIndex } from '@/routes/customers';

type Entry = {
    id: number;
    type: string;
    type_label: string;
    amount: string;
    balance: string;
    bill_id: number | null;
    bill: string | null;
    related: string | null;
    method: string | null;
    reference: string | null;
    note: string | null;
    by: string;
    created_at: string;
};

const props = defineProps<{
    customer: {
        id: number;
        name: string;
        phone: string | null;
        credit_limit: string;
        is_active: boolean;
        email: string | null;
        birthday: string | null;
        anniversary: string | null;
        preferred_seat: string | null;
    };
    summary: {
        balance: string;
        age_0_30: string;
        age_31_60: string;
        age_60_plus: string;
        oldest: string | null;
    };
    entries: Entry[];
    history: {
        visits: number;
        spent: string;
        last_visit: string | null;
        favourites: { name: string; qty: number }[];
        spent_12m: string;
        tier: string;
    };
    points: number;
    pointValue: string;
    loyalty: {
        id: number;
        points: number;
        type: string;
        note: string | null;
        expires_on: string | null;
        created_at: string;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers & dues', href: customersIndex() }],
    },
});

const { can } = usePermissions();
function when(iso: string): string {
    return new Date(iso).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
function whatsapp(): string {
    const phone = (props.customer.phone ?? '')
        .replace(/\D/g, '')
        .replace(/^(?!977)(9\d{9})$/, '977$1');
    const text = `Namaste ${props.customer.name}, your due at our restaurant is ${rs(props.summary.balance)} as of today. Thank you.`;
    return `https://wa.me/${phone}?text=${encodeURIComponent(text)}`;
}
function printBill(id: number): void {
    window.open(BillController.print(id).url, '_blank', 'width=380,height=700');
}
</script>

<template>
    <Head :title="customer.name" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center gap-3">
            <Link
                :href="customersIndex().url"
                class="flex size-11 items-center justify-center rounded-lg border"
                aria-label="Back"
                ><ArrowLeft class="size-5"
            /></Link>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    {{ customer.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ customer.phone ?? 'No phone' }} · limit
                    {{ rs(customer.credit_limit)
                    }}{{ customer.is_active ? '' : ' · turned off' }}
                </p>
            </div>
            <div class="ml-auto flex gap-2">
                <a
                    :href="CustomerController.statement(customer.id).url"
                    target="_blank"
                    class="flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-semibold"
                    ><Printer class="size-4" /> Statement</a
                >
                <a
                    v-if="customer.phone"
                    :href="whatsapp()"
                    target="_blank"
                    rel="noopener"
                    class="flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-semibold"
                    ><MessageCircle class="size-4" /> WhatsApp</a
                >
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">Visits</div>
                <div class="text-xl font-bold tabular-nums">
                    {{ history.visits }}
                </div>
                <div class="text-xs text-muted-foreground">
                    last
                    {{
                        history.last_visit
                            ? history.last_visit.slice(0, 10)
                            : '—'
                    }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">Total spent</div>
                <div class="text-xl font-bold tabular-nums">
                    {{ rs(history.spent) }}
                </div>
                <div class="text-xs text-muted-foreground">
                    12 months {{ rs(history.spent_12m) }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">Tier</div>
                <div
                    class="text-xl font-bold"
                    :class="
                        history.tier === 'Gold'
                            ? 'text-amber-600'
                            : history.tier === 'Silver'
                              ? 'text-slate-500'
                              : ''
                    "
                >
                    {{ history.tier }}
                </div>
                <div class="text-xs text-muted-foreground">
                    {{
                        customer.preferred_seat
                            ? `likes ${customer.preferred_seat}`
                            : ''
                    }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">Loyalty points</div>
                <div class="text-xl font-bold tabular-nums">{{ points }}</div>
                <div class="text-xs text-muted-foreground">
                    worth
                    {{ rs(Math.round(points * Number(pointValue) * 100)) }}
                </div>
            </div>
        </div>
        <p v-if="history.favourites.length" class="text-sm">
            <b>Favourites:</b>
            {{ history.favourites.map((f) => `${f.name} (${f.qty})`).join(', ')
            }}<span v-if="customer.birthday">
                · <b>Birthday</b> {{ customer.birthday.slice(5) }}</span
            ><span v-if="customer.anniversary">
                · <b>Anniversary</b> {{ customer.anniversary.slice(5) }}</span
            >
        </p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">Due now</div>
                <div class="text-xl font-bold tabular-nums">
                    {{ rs(summary.balance) }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">0–30 days</div>
                <div class="text-xl font-bold tabular-nums">
                    {{ rs(summary.age_0_30) }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">31–60 days</div>
                <div class="text-xl font-bold tabular-nums">
                    {{ rs(summary.age_31_60) }}
                </div>
            </div>
            <div class="rounded-xl border p-3">
                <div class="text-xs text-muted-foreground">60+ days</div>
                <div class="text-xl font-bold tabular-nums">
                    {{ rs(summary.age_60_plus) }}
                </div>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">What</th>
                        <th class="p-3">Details</th>
                        <th class="p-3 text-right">Amount</th>
                        <th class="p-3 text-right">Due after</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in entries" :key="e.id" class="border-t">
                        <td class="p-3 text-muted-foreground">
                            {{ when(e.created_at) }}
                        </td>
                        <td class="p-3 font-semibold">
                            {{ e.type_label }}
                            <div
                                class="text-xs font-normal text-muted-foreground"
                            >
                                by {{ e.by }}
                            </div>
                        </td>
                        <td class="p-3">
                            <button
                                v-if="e.bill_id && can('billing.take_payment')"
                                type="button"
                                class="inline-flex items-center gap-1 underline"
                                @click="printBill(e.bill_id)"
                            >
                                <Printer class="size-3" /> Bill {{ e.bill }}
                            </button>
                            <span v-else-if="e.bill">Bill {{ e.bill }}</span>
                            <span v-if="e.related">
                                {{ e.type === 'transfer_out' ? 'to' : 'from' }}
                                {{ e.related }}</span
                            >
                            <span v-if="e.method">
                                {{ e.method
                                }}{{
                                    e.reference ? ' #' + e.reference : ''
                                }}</span
                            >
                            <div
                                v-if="e.note"
                                class="text-xs text-muted-foreground"
                            >
                                {{ e.note }}
                            </div>
                        </td>
                        <td
                            class="p-3 text-right font-semibold tabular-nums"
                            :class="
                                toPaisa(e.amount) < 0
                                    ? 'text-green-700 dark:text-green-400'
                                    : ''
                            "
                        >
                            {{
                                toPaisa(e.amount) < 0
                                    ? '− ' + rs(-toPaisa(e.amount))
                                    : rs(e.amount)
                            }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ rs(e.balance) }}
                        </td>
                    </tr>
                    <tr v-if="entries.length === 0">
                        <td
                            colspan="5"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No dues yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <details class="rounded-xl border p-3">
            <summary class="cursor-pointer text-sm font-semibold">
                Points history
            </summary>
            <div class="mt-2 flex flex-col divide-y text-sm">
                <div
                    v-for="l in loyalty"
                    :key="l.id"
                    class="flex justify-between py-1.5"
                >
                    <span
                        >{{ l.created_at.slice(0, 10) }} · {{ l.type
                        }}<span class="text-xs text-muted-foreground">
                            {{ l.note
                            }}{{
                                l.expires_on ? ` · expires ${l.expires_on}` : ''
                            }}</span
                        ></span
                    >
                    <span
                        class="font-semibold tabular-nums"
                        :class="
                            l.points < 0 ? 'text-red-600' : 'text-green-700'
                        "
                        >{{ l.points > 0 ? '+' : '' }}{{ l.points }}</span
                    >
                </div>
                <p
                    v-if="loyalty.length === 0"
                    class="py-2 text-muted-foreground"
                >
                    No points yet.
                </p>
            </div>
        </details>
    </div>
</template>
