<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Paperclip, Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/Money/ExpenseController';
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
import { rs } from '@/lib/money';
import { index as expensesIndex } from '@/routes/money/expenses';

type Expense = {
    id: number;
    number: string;
    spent_on: string;
    category: string;
    description: string;
    amount: string;
    account: string;
    kind: string;
    by: string;
    has_receipt: boolean;
    voided: boolean;
    void_reason: string | null;
};
type Recurring = {
    id: number;
    category_id: number;
    category: string;
    description: string;
    amount: string;
    day_of_month: number;
    is_active: boolean;
    due: boolean;
};
type Option = { id: number; name: string };

const props = defineProps<{
    filters: { from: string; to: string; category: number | null };
    expenses: Expense[];
    total: string;
    categories: (Option & { kind: string })[];
    accounts: Option[];
    recurring: Recurring[];
    today: string;
    can: { manage: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Expenses', href: expensesIndex() }] },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const category = ref<number | null>(props.filters.category);
function load(): void {
    router.visit(
        expensesIndex({
            query: {
                from: from.value,
                to: to.value,
                category: category.value ?? undefined,
            },
        }),
        { preserveScroll: true, preserveState: true },
    );
}

const panel = ref<'add' | 'recurring' | null>(null);
const form = useForm({
    expense_category_id: props.categories[0]?.id ?? null,
    description: '',
    amount: '',
    account_id: props.accounts[0]?.id ?? null,
    spent_on: props.today,
    receipt: null as File | null,
    recurring_id: null as number | null,
});
function add(r: Recurring | null = null): void {
    form.reset();
    form.clearErrors();
    form.spent_on = props.today;
    if (r) {
        form.expense_category_id = r.category_id;
        form.description = r.description;
        form.amount = r.amount;
        form.recurring_id = r.id;
    }
    panel.value = 'add';
}
function save(): void {
    form.submit(ExpenseController.store(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (panel.value = null),
    });
}
function voidExpense(e: Expense): void {
    const reason = window.prompt(`Why void ${e.number}?`);
    if (!reason) return;
    router.visit(ExpenseController.void(e.id), {
        data: { reason },
        preserveScroll: true,
    });
}

const recForm = useForm({
    expense_category_id: props.categories[0]?.id ?? null,
    description: '',
    amount: '',
    day_of_month: 1,
    is_active: true,
});
function saveRecurring(): void {
    recForm.submit(ExpenseController.storeRecurring(), {
        preserveScroll: true,
        onSuccess: () => recForm.reset(),
    });
}
function toggleRecurring(r: Recurring): void {
    router.visit(ExpenseController.updateRecurring(r.id), {
        data: {
            expense_category_id: r.category_id,
            description: r.description,
            amount: r.amount,
            day_of_month: r.day_of_month,
            is_active: !r.is_active,
        },
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Expenses" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Expenses</h1>
                <p class="text-sm text-muted-foreground">
                    Total {{ rs(total) }} in this period (void entries not
                    counted)
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
                <select
                    v-model="category"
                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                >
                    <option :value="null">All categories</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
                <Button variant="outline" class="h-10" @click="load"
                    >Show</Button
                >
                <Button
                    v-if="can.manage"
                    variant="outline"
                    class="h-10"
                    @click="panel = 'recurring'"
                    ><CalendarClock class="size-4" /> Monthly</Button
                >
                <Button class="h-10" @click="add()"
                    ><Plus class="size-4" /> Add expense</Button
                >
            </div>
        </div>

        <div
            v-for="r in recurring.filter((x) => x.due)"
            :key="r.id"
            class="flex flex-wrap items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <CalendarClock class="size-4" /> <b>{{ r.description }}</b> ({{
                r.category
            }}) {{ rs(r.amount) }} is due this month.
            <Button size="sm" class="ml-auto" @click="add(r)"
                >Confirm & pay</Button
            >
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Category</th>
                        <th class="p-3">Description</th>
                        <th class="p-3">Paid from</th>
                        <th class="p-3 text-right">Amount</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in expenses"
                        :key="e.id"
                        class="border-t"
                        :class="e.voided ? 'opacity-50' : ''"
                    >
                        <td class="p-3 tabular-nums">
                            {{ e.spent_on }}
                            <div class="text-xs text-muted-foreground">
                                {{ e.number }}
                            </div>
                        </td>
                        <td class="p-3">
                            {{ e.category }}
                            <div
                                v-if="e.kind === 'investment'"
                                class="text-xs font-semibold text-violet-700 dark:text-violet-300"
                            >
                                Investment
                            </div>
                        </td>
                        <td class="p-3" :class="e.voided ? 'line-through' : ''">
                            {{ e.description }}
                            <div class="text-xs text-muted-foreground">
                                by {{ e.by }}
                            </div>
                            <div
                                v-if="e.voided"
                                class="text-xs text-red-600 no-underline"
                            >
                                Void: {{ e.void_reason }}
                            </div>
                        </td>
                        <td class="p-3">{{ e.account }}</td>
                        <td class="p-3 text-right font-bold tabular-nums">
                            {{ rs(e.amount) }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1.5">
                                <a
                                    v-if="e.has_receipt"
                                    :href="ExpenseController.receipt(e.id).url"
                                    class="flex size-9 items-center justify-center rounded-md border"
                                    aria-label="Receipt"
                                    ><Paperclip class="size-3.5"
                                /></a>
                                <button
                                    v-if="can.manage && !e.voided"
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-md border text-red-600"
                                    aria-label="Void"
                                    @click="voidExpense(e)"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="expenses.length === 0">
                        <td
                            colspan="6"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No expenses in this period.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Sheet :open="panel !== null" @update:open="(v) => !v && (panel = null)">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <template v-if="panel === 'add'">
                <SheetHeader
                    ><SheetTitle>Add expense</SheetTitle
                    ><SheetDescription
                        >The money is taken out of the account you
                        choose.</SheetDescription
                    ></SheetHeader
                >
                <form class="flex flex-col gap-3 px-4" @submit.prevent="save">
                    <div class="grid gap-2">
                        <Label>Category</Label>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="c in categories"
                                :key="c.id"
                                type="button"
                                class="h-9 rounded-full border px-3 text-sm"
                                :class="
                                    form.expense_category_id === c.id
                                        ? 'border-foreground bg-foreground text-background'
                                        : ''
                                "
                                @click="form.expense_category_id = c.id"
                            >
                                {{ c.name }}
                            </button>
                        </div>
                        <InputError
                            :message="form.errors.expense_category_id"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="d">Description</Label
                        ><Input id="d" v-model="form.description" /><InputError
                            :message="form.errors.description"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="grid gap-2">
                            <Label for="a">Amount (Rs)</Label
                            ><Input
                                id="a"
                                v-model="form.amount"
                                inputmode="decimal"
                            /><InputError :message="form.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="s">Date</Label
                            ><Input
                                id="s"
                                v-model="form.spent_on"
                                type="date"
                                :max="today"
                            /><InputError :message="form.errors.spent_on" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Paid from</Label>
                        <select
                            v-model="form.account_id"
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
                        <Label for="r">Receipt photo (optional)</Label>
                        <input
                            id="r"
                            type="file"
                            accept="image/*,application/pdf"
                            class="text-sm"
                            @change="
                                form.receipt =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                        <InputError :message="form.errors.receipt" />
                    </div>
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="form.processing"
                        >Save expense</Button
                    >
                </form>
            </template>
            <template v-else-if="panel === 'recurring'">
                <SheetHeader
                    ><SheetTitle>Monthly expenses</SheetTitle
                    ><SheetDescription
                        >Rent, electricity, water… a reminder shows each month
                        to confirm.</SheetDescription
                    ></SheetHeader
                >
                <div class="flex flex-col gap-2 px-4">
                    <div
                        v-for="r in recurring"
                        :key="r.id"
                        class="flex items-center justify-between gap-2 rounded-lg border p-2 text-sm"
                        :class="r.is_active ? '' : 'opacity-50'"
                    >
                        <span
                            ><b>{{ r.description }}</b> · {{ r.category
                            }}<span class="block text-xs text-muted-foreground"
                                >{{ rs(r.amount) }} on day
                                {{ r.day_of_month }}</span
                            ></span
                        >
                        <button
                            type="button"
                            class="h-8 rounded-md border px-2 text-xs"
                            @click="toggleRecurring(r)"
                        >
                            {{ r.is_active ? 'Stop' : 'Start' }}
                        </button>
                    </div>
                </div>
                <form
                    class="flex flex-col gap-2 border-t px-4 pt-3"
                    @submit.prevent="saveRecurring"
                >
                    <select
                        v-model="recForm.expense_category_id"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <Input
                        v-model="recForm.description"
                        placeholder="e.g. Rent"
                    />
                    <InputError :message="recForm.errors.description" />
                    <div class="grid grid-cols-2 gap-2">
                        <Input
                            v-model="recForm.amount"
                            inputmode="decimal"
                            placeholder="Amount"
                        />
                        <Input
                            v-model.number="recForm.day_of_month"
                            type="number"
                            min="1"
                            max="28"
                            placeholder="Day"
                        />
                    </div>
                    <InputError
                        :message="
                            recForm.errors.amount || recForm.errors.day_of_month
                        "
                    />
                    <Button
                        type="submit"
                        class="h-11"
                        :disabled="recForm.processing"
                        >Add monthly expense</Button
                    >
                </form>
            </template>
        </SheetContent>
    </Sheet>
</template>
