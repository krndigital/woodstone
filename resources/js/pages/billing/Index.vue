<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Printer, Undo2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { computed, ref } from 'vue';
import BillController from '@/actions/App/Http/Controllers/Billing/BillController';
import { Input } from '@/components/ui/input';
import { rs, toPaisa } from '@/lib/money';
import { index as billingIndex } from '@/routes/billing';

type Bill = {
    id: number;
    number: string;
    label: string;
    total: string;
    paid: string;
    due: string;
    discount: string;
    methods: string[];
    cashier: string;
    print_count: number;
    returned: string;
    has_due: boolean;
    created_at: string | null;
};

const props = defineProps<{
    bills: Bill[];
    methods: { value: string; label: string }[];
    can: { approve: boolean };
}>();

const returning = ref<Bill | null>(null);
const returnForm = useForm({
    full_cancel: true,
    amount: '',
    refund_method: 'cash',
    restock: true,
    reason: '',
    approver_username: '',
    approver_secret: '',
});
const left = (b: Bill): number => toPaisa(b.total) - toPaisa(b.returned);
function startReturn(b: Bill): void {
    returnForm.reset();
    returnForm.clearErrors();
    returnForm.refund_method = b.has_due ? 'due' : 'cash';
    returning.value = b;
}
function submitReturn(): void {
    if (!returning.value) return;
    returnForm
        .transform((d) => ({ ...d, restock: d.full_cancel && d.restock }))
        .submit(BillController.returnSale(returning.value.id), {
            preserveScroll: true,
            onSuccess: () => (returning.value = null),
            onFinish: () => (returnForm.approver_secret = ''),
        });
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Bills', href: billingIndex() }] },
});

const q = ref('');
const shown = computed(() => {
    const s = q.value.trim().toLowerCase();
    return s === ''
        ? props.bills
        : props.bills.filter(
              (b) =>
                  b.number.toLowerCase().includes(s) ||
                  b.label.toLowerCase().includes(s),
          );
});
const totalShown = computed(() =>
    shown.value.reduce((a, b) => a + toPaisa(b.total), 0),
);

function print(b: Bill): void {
    window.open(
        BillController.print(b.id).url,
        '_blank',
        'width=380,height=700',
    );
}
function when(iso: string | null): string {
    return iso === null
        ? ''
        : new Date(iso).toLocaleString('en-GB', {
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          });
}
</script>

<template>
    <Head title="Bills" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Bills</h1>
                <p class="text-sm text-muted-foreground">
                    Last 100 bills · reprints are marked COPY and recorded
                </p>
            </div>
            <Input
                v-model="q"
                class="ml-auto h-10 w-64"
                placeholder="Search bill no. or table"
            />
        </div>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Bill</th>
                        <th class="p-3">Table / order</th>
                        <th class="p-3">Time</th>
                        <th class="p-3">Paid by</th>
                        <th class="p-3 text-right">Discount</th>
                        <th class="p-3 text-right">On due</th>
                        <th class="p-3 text-right">Total</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in shown" :key="b.id" class="border-t">
                        <td class="p-3 font-semibold tabular-nums">
                            {{ b.number }}
                        </td>
                        <td class="p-3">
                            {{ b.label }}
                            <div class="text-xs text-muted-foreground">
                                {{ b.cashier }}
                            </div>
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ when(b.created_at) }}
                        </td>
                        <td class="p-3">{{ b.methods.join(', ') || '—' }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ toPaisa(b.discount) ? rs(b.discount) : '—' }}
                        </td>
                        <td
                            class="p-3 text-right tabular-nums"
                            :class="
                                toPaisa(b.due)
                                    ? 'font-semibold text-amber-700 dark:text-amber-400'
                                    : ''
                            "
                        >
                            {{ toPaisa(b.due) ? rs(b.due) : '—' }}
                        </td>
                        <td class="p-3 text-right font-bold tabular-nums">
                            {{ rs(b.total) }}
                            <div
                                v-if="toPaisa(b.returned)"
                                class="text-xs font-semibold text-red-600"
                            >
                                Returned {{ rs(b.returned) }}
                            </div>
                        </td>
                        <td class="p-3 text-right">
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-1 rounded-md border px-3 text-xs font-semibold"
                                @click="print(b)"
                            >
                                <Printer class="size-3.5" />
                                {{ b.print_count ? 'Reprint' : 'Print' }}
                            </button>
                            <button
                                v-if="left(b) > 0"
                                type="button"
                                class="ml-1.5 inline-flex h-9 items-center gap-1 rounded-md border px-3 text-xs font-semibold text-red-600"
                                @click="startReturn(b)"
                            >
                                <Undo2 class="size-3.5" /> Return
                            </button>
                        </td>
                    </tr>
                    <tr v-if="shown.length === 0">
                        <td
                            colspan="8"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No bills yet.
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="shown.length">
                    <tr class="border-t bg-muted/50 font-bold">
                        <td colspan="6" class="p-3">
                            Total ({{ shown.length }} bills)
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ rs(totalShown) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <Dialog
        :open="returning !== null"
        @update:open="(v) => !v && (returning = null)"
    >
        <DialogContent v-if="returning">
            <DialogHeader>
                <DialogTitle>Return on bill {{ returning.number }}</DialogTitle>
                <DialogDescription
                    >Up to {{ rs(left(returning)) }}. The bill stays as printed;
                    the return is recorded with a reason and
                    approval.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3" @submit.prevent="submitReturn">
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="h-11 rounded-lg border font-semibold"
                        :class="
                            returnForm.full_cancel
                                ? 'border-foreground bg-foreground text-background'
                                : ''
                        "
                        @click="returnForm.full_cancel = true"
                    >
                        Cancel whole bill
                    </button>
                    <button
                        type="button"
                        class="h-11 rounded-lg border font-semibold"
                        :class="
                            !returnForm.full_cancel
                                ? 'border-foreground bg-foreground text-background'
                                : ''
                        "
                        @click="returnForm.full_cancel = false"
                    >
                        Part amount
                    </button>
                </div>
                <input
                    v-if="!returnForm.full_cancel"
                    v-model="returnForm.amount"
                    inputmode="decimal"
                    placeholder="Amount (Rs)"
                    class="h-10 rounded-lg border bg-background px-3"
                />
                <InputError :message="returnForm.errors.amount" />
                <label class="text-sm font-semibold">Refund by</label>
                <select
                    v-model="returnForm.refund_method"
                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                >
                    <option
                        v-for="m in methods"
                        :key="m.value"
                        :value="m.value"
                    >
                        {{
                            m.value === 'due'
                                ? 'Take off customer due'
                                : m.label
                        }}
                    </option>
                </select>
                <InputError :message="returnForm.errors.refund_method" />
                <label
                    v-if="returnForm.full_cancel"
                    class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="returnForm.restock"
                        type="checkbox"
                        class="size-4"
                    />
                    Put ingredients back into stock (food not made)</label
                >
                <input
                    v-model="returnForm.reason"
                    placeholder="Reason"
                    class="h-10 rounded-lg border bg-background px-3"
                />
                <InputError
                    :message="
                        returnForm.errors.reason || returnForm.errors.restock
                    "
                />
                <template v-if="!can.approve">
                    <input
                        v-model="returnForm.approver_username"
                        placeholder="Admin username"
                        autocomplete="off"
                        class="h-10 rounded-lg border bg-background px-3"
                    />
                    <input
                        v-model="returnForm.approver_secret"
                        type="password"
                        placeholder="Admin PIN or password"
                        autocomplete="off"
                        class="h-10 rounded-lg border bg-background px-3"
                    />
                </template>
                <InputError :message="returnForm.errors.approver_username" />
                <Button
                    type="submit"
                    variant="destructive"
                    class="h-12"
                    :disabled="returnForm.processing"
                    >Save return</Button
                >
            </form>
        </DialogContent>
    </Dialog>
</template>
