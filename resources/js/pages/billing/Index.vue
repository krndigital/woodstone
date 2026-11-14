<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
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
    created_at: string | null;
};

const props = defineProps<{ bills: Bill[] }>();

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
</template>
