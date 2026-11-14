<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AccountingController from '@/actions/App/Http/Controllers/Accounting/AccountingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { rs, toPaisa } from '@/lib/money';
import { index as accountingIndex } from '@/routes/accounting';

type Account = {
    code: string;
    name: string;
    type: 'asset' | 'liability' | 'equity' | 'income' | 'expense';
    balance: string;
};
type Journal = {
    id: number;
    date: string;
    source: string;
    memo: string | null;
    lines: { code: string; name: string; debit: string; credit: string }[];
};

const props = defineProps<{
    accounts: Account[];
    chart: { code: string; name: string; type: string }[];
    journals: Journal[];
    today: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Accounting', href: accountingIndex() }] },
});

const TYPES: Account['type'][] = [
    'asset',
    'liability',
    'equity',
    'income',
    'expense',
];
const LABEL: Record<Account['type'], string> = {
    asset: 'Assets',
    liability: 'Liabilities',
    equity: 'Equity',
    income: 'Income',
    expense: 'Expenses',
};
// Assets and expenses are debit balances; the rest are shown as credit balances.
const shown = (a: Account): number =>
    a.type === 'asset' || a.type === 'expense'
        ? toPaisa(a.balance)
        : -toPaisa(a.balance);
const open = ref<number | null>(null);

type Line = { code: string; debit: string; credit: string };
const form = useForm({
    entry_date: props.today,
    memo: '',
    lines: [
        { code: '', debit: '', credit: '' },
        { code: '', debit: '', credit: '' },
    ] as Line[],
});
const dr = computed(() => form.lines.reduce((a, l) => a + toPaisa(l.debit), 0));
const cr = computed(() =>
    form.lines.reduce((a, l) => a + toPaisa(l.credit), 0),
);
function save(): void {
    form.submit(AccountingController.store(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
function errorFor(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}
</script>

<template>
    <Head title="Accounting" />
    <div class="flex flex-col gap-5 p-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Accounting</h1>
            <p class="text-sm text-muted-foreground">
                The ledger is written automatically from bills, cash, purchases,
                stock and salary. Balance sheet, trial balance and cash flow are
                in Reports.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-5">
            <section
                v-for="t in TYPES"
                :key="t"
                class="flex flex-col gap-1 rounded-xl border p-3 text-sm"
            >
                <h2 class="font-bold">{{ LABEL[t] }}</h2>
                <div
                    v-for="a in accounts.filter((x) => x.type === t)"
                    :key="a.code"
                    class="flex justify-between gap-2"
                >
                    <span class="truncate"
                        ><span class="text-xs text-muted-foreground">{{
                            a.code
                        }}</span>
                        {{ a.name }}</span
                    >
                    <span
                        class="tabular-nums"
                        :class="shown(a) < 0 ? 'text-red-600' : ''"
                        >{{ rs(shown(a)) }}</span
                    >
                </div>
            </section>
        </div>

        <section class="flex flex-col gap-3 rounded-xl border p-4">
            <h2 class="font-bold">Manual journal (corrections only)</h2>
            <div class="grid gap-2 sm:grid-cols-[160px_1fr]">
                <Input v-model="form.entry_date" type="date" :max="today" />
                <Input
                    v-model="form.memo"
                    placeholder="Why this correction is needed"
                />
            </div>
            <InputError :message="form.errors.entry_date || form.errors.memo" />
            <div
                v-for="(l, i) in form.lines"
                :key="i"
                class="grid grid-cols-[1fr_120px_120px_36px] gap-2"
            >
                <select
                    v-model="l.code"
                    class="h-10 rounded-lg border bg-background px-2 text-sm"
                >
                    <option value="" disabled>Account</option>
                    <option v-for="c in chart" :key="c.code" :value="c.code">
                        {{ c.code }} {{ c.name }}
                    </option>
                </select>
                <Input
                    v-model="l.debit"
                    inputmode="decimal"
                    placeholder="Debit"
                />
                <Input
                    v-model="l.credit"
                    inputmode="decimal"
                    placeholder="Credit"
                />
                <button
                    type="button"
                    class="flex size-10 items-center justify-center rounded-md border"
                    :disabled="form.lines.length <= 2"
                    @click="form.lines.splice(i, 1)"
                >
                    <Trash2 class="size-4" />
                </button>
                <p
                    v-if="errorFor(`lines.${i}.code`)"
                    class="col-span-4 text-xs text-red-600"
                >
                    {{ errorFor(`lines.${i}.code`) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10"
                    @click="
                        form.lines.push({ code: '', debit: '', credit: '' })
                    "
                    ><Plus class="size-4" /> Line</Button
                >
                <span
                    class="text-sm tabular-nums"
                    :class="
                        dr === cr && dr > 0 ? 'text-green-700' : 'text-red-600'
                    "
                    >Debit {{ rs(dr) }} · Credit {{ rs(cr) }}</span
                >
                <Button
                    class="ml-auto h-10"
                    :disabled="form.processing || dr !== cr || dr === 0"
                    @click="save"
                    >Post journal</Button
                >
            </div>
            <InputError :message="form.errors.lines" />
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="font-bold">Latest journals</h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted text-left">
                        <tr>
                            <th class="p-3">Date</th>
                            <th class="p-3">From</th>
                            <th class="p-3">Memo</th>
                            <th class="p-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="j in journals" :key="j.id">
                            <tr
                                class="cursor-pointer border-t hover:bg-muted/50"
                                @click="open = open === j.id ? null : j.id"
                            >
                                <td class="p-3 tabular-nums">{{ j.date }}</td>
                                <td class="p-3">{{ j.source }}</td>
                                <td class="p-3">{{ j.memo }}</td>
                                <td class="p-3 text-right tabular-nums">
                                    {{
                                        rs(
                                            j.lines.reduce(
                                                (a, l) => a + toPaisa(l.debit),
                                                0,
                                            ),
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr v-if="open === j.id" class="bg-muted/30">
                                <td colspan="4" class="p-3">
                                    <div
                                        v-for="(l, i) in j.lines"
                                        :key="i"
                                        class="grid grid-cols-[1fr_120px_120px] gap-2 text-xs"
                                    >
                                        <span>{{ l.code }} {{ l.name }}</span>
                                        <span class="text-right tabular-nums">{{
                                            toPaisa(l.debit) ? rs(l.debit) : ''
                                        }}</span>
                                        <span class="text-right tabular-nums">{{
                                            toPaisa(l.credit)
                                                ? rs(l.credit)
                                                : ''
                                        }}</span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="journals.length === 0">
                            <td
                                colspan="4"
                                class="p-8 text-center text-muted-foreground"
                            >
                                No journals yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
