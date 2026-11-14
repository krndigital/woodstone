<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowRightLeft, Plus } from '@lucide/vue';
import { ref } from 'vue';
import CashBookController from '@/actions/App/Http/Controllers/Money/CashBookController';
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
import { cashBook } from '@/routes/money';

type Account = {
    id: number;
    code: string;
    name: string;
    opening: string;
    in: string;
    out: string;
    closing: string;
    balance: string;
};
type Row = {
    id: number;
    date: string;
    source: string;
    category: string | null;
    note: string | null;
    amount: string;
    balance: string;
    by: string;
};

const props = defineProps<{
    filters: { from: string; to: string; account: number | null };
    accounts: Account[];
    rows: Row[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Cash book', href: cashBook() }] },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
function load(account: number | null = props.filters.account): void {
    router.visit(
        cashBook({
            query: {
                from: from.value,
                to: to.value,
                account: account ?? undefined,
            },
        }),
        { preserveScroll: true, preserveState: true },
    );
}
const SOURCE: Record<string, string> = {
    bill_payment: 'Sale',
    due_receipt: 'Due received',
    purchase: 'Purchase',
    supplier_payment: 'Supplier paid',
    expense: 'Expense',
    expense_void: 'Expense void',
    salary: 'Salary',
    advance: 'Staff advance',
    manual: 'Manual',
    transfer: 'Transfer',
    sales_return: 'Refund',
    day_close: 'Day close',
};

const panel = ref<'manual' | 'transfer' | null>(null);
const manual = useForm({
    account_id: props.filters.account,
    direction: 'in' as 'in' | 'out',
    amount: '',
    category: 'owner_capital',
    note: '',
});
const transfer = useForm({
    from_account_id: props.accounts.find((a) => a.code === 'cash')?.id ?? null,
    to_account_id: props.accounts.find((a) => a.code === 'bank')?.id ?? null,
    amount: '',
    note: '',
});
function saveManual(): void {
    manual.submit(CashBookController.manual(), {
        preserveScroll: true,
        onSuccess: () => {
            panel.value = null;
            manual.reset('amount', 'note');
        },
    });
}
function saveTransfer(): void {
    transfer.submit(CashBookController.transfer(), {
        preserveScroll: true,
        onSuccess: () => {
            panel.value = null;
            transfer.reset('amount', 'note');
        },
    });
}
</script>

<template>
    <Head title="Cash book" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Cash book</h1>
                <p class="text-sm text-muted-foreground">
                    Every sale, purchase, expense and transfer lands here
                    automatically.
                </p>
            </div>
            <div class="ml-auto flex flex-wrap items-end gap-2">
                <div class="grid gap-1">
                    <Label class="text-xs">From</Label
                    ><Input v-model="from" type="date" class="h-10" />
                </div>
                <div class="grid gap-1">
                    <Label class="text-xs">To</Label
                    ><Input v-model="to" type="date" class="h-10" />
                </div>
                <Button variant="outline" class="h-10" @click="load()"
                    >Show</Button
                >
                <Button
                    variant="outline"
                    class="h-10"
                    @click="panel = 'transfer'"
                    ><ArrowRightLeft class="size-4" /> Transfer</Button
                >
                <Button class="h-10" @click="panel = 'manual'"
                    ><Plus class="size-4" /> Cash in / out</Button
                >
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button
                v-for="a in accounts"
                :key="a.id"
                type="button"
                class="flex flex-col gap-1 rounded-xl border p-4 text-left"
                :class="
                    filters.account === a.id
                        ? 'border-foreground ring-1 ring-foreground'
                        : ''
                "
                @click="load(a.id)"
            >
                <span class="text-sm font-semibold">{{ a.name }}</span>
                <span class="text-2xl font-bold tabular-nums">{{
                    rs(a.balance)
                }}</span>
                <span class="text-xs text-muted-foreground tabular-nums"
                    >Period: open {{ rs(a.opening) }} · in {{ rs(a.in) }} · out
                    {{ rs(a.out) }} · close {{ rs(a.closing) }}</span
                >
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">What</th>
                        <th class="p-3">Details</th>
                        <th class="p-3 text-right">In</th>
                        <th class="p-3 text-right">Out</th>
                        <th class="p-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id" class="border-t">
                        <td class="p-3 tabular-nums">{{ r.date }}</td>
                        <td class="p-3 font-semibold">
                            {{ SOURCE[r.source] ?? r.source }}
                            <div
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ r.by }}
                            </div>
                        </td>
                        <td class="p-3">
                            {{ r.note
                            }}<span
                                v-if="r.category"
                                class="text-xs text-muted-foreground"
                            >
                                ({{ r.category.replace('_', ' ') }})</span
                            >
                        </td>
                        <td
                            class="p-3 text-right text-green-700 tabular-nums dark:text-green-400"
                        >
                            {{ toPaisa(r.amount) > 0 ? rs(r.amount) : '' }}
                        </td>
                        <td class="p-3 text-right text-red-600 tabular-nums">
                            {{
                                toPaisa(r.amount) < 0
                                    ? rs(-toPaisa(r.amount))
                                    : ''
                            }}
                        </td>
                        <td class="p-3 text-right font-semibold tabular-nums">
                            {{ rs(r.balance) }}
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td
                            colspan="6"
                            class="p-10 text-center text-muted-foreground"
                        >
                            Nothing in this period.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Sheet :open="panel !== null" @update:open="(v) => !v && (panel = null)">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <template v-if="panel === 'manual'">
                <SheetHeader
                    ><SheetTitle>Cash in / out</SheetTitle
                    ><SheetDescription
                        >Owner money and loans are kept apart from
                        sales.</SheetDescription
                    ></SheetHeader
                >
                <form
                    class="flex flex-col gap-3 px-4"
                    @submit.prevent="saveManual"
                >
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="d in ['in', 'out'] as const"
                            :key="d"
                            type="button"
                            class="h-11 rounded-lg border font-semibold"
                            :class="
                                manual.direction === d
                                    ? 'border-foreground bg-foreground text-background'
                                    : ''
                            "
                            @click="manual.direction = d"
                        >
                            {{ d === 'in' ? 'Money in' : 'Money out' }}
                        </button>
                    </div>
                    <select
                        v-model="manual.account_id"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option v-for="a in accounts" :key="a.id" :value="a.id">
                            {{ a.name }}
                        </option>
                    </select>
                    <select
                        v-model="manual.category"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option value="owner_capital">Owner investment</option>
                        <option value="owner_withdrawal">
                            Owner withdrawal
                        </option>
                        <option value="loan">Loan</option>
                        <option value="float">Cash float</option>
                        <option value="other">Other</option>
                    </select>
                    <Input
                        v-model="manual.amount"
                        inputmode="decimal"
                        placeholder="Amount (Rs)"
                    />
                    <InputError
                        :message="
                            manual.errors.amount || manual.errors.account_id
                        "
                    />
                    <Input v-model="manual.note" placeholder="Reason" />
                    <InputError :message="manual.errors.note" />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="manual.processing"
                        >Save</Button
                    >
                </form>
            </template>
            <template v-else-if="panel === 'transfer'">
                <SheetHeader
                    ><SheetTitle>Transfer between accounts</SheetTitle
                    ><SheetDescription
                        >For example cash deposited to the
                        bank.</SheetDescription
                    ></SheetHeader
                >
                <form
                    class="flex flex-col gap-3 px-4"
                    @submit.prevent="saveTransfer"
                >
                    <Label>From</Label>
                    <select
                        v-model="transfer.from_account_id"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option v-for="a in accounts" :key="a.id" :value="a.id">
                            {{ a.name }} ({{ rs(a.balance) }})
                        </option>
                    </select>
                    <Label>To</Label>
                    <select
                        v-model="transfer.to_account_id"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option v-for="a in accounts" :key="a.id" :value="a.id">
                            {{ a.name }}
                        </option>
                    </select>
                    <InputError :message="transfer.errors.to_account_id" />
                    <Input
                        v-model="transfer.amount"
                        inputmode="decimal"
                        placeholder="Amount (Rs)"
                    />
                    <InputError :message="transfer.errors.amount" />
                    <Input
                        v-model="transfer.note"
                        placeholder="Note (optional)"
                    />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="transfer.processing"
                        >Transfer</Button
                    >
                </form>
            </template>
        </SheetContent>
    </Sheet>
</template>
