<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import { computed, ref } from 'vue';
import PayrollController from '@/actions/App/Http/Controllers/Staff/PayrollController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { rs, toPaisa } from '@/lib/money';
import { payroll } from '@/routes/staff';

type Slip = {
    id: number;
    staff: string;
    position: string;
    salary_type: string;
    working_days: number;
    present_days: number;
    late_days: number;
    leave_days: number;
    absent_days: number;
    base_salary: string;
    earned: string;
    advance_deduction: string;
    other_deduction: string;
    bonus: string;
    net: string;
    status: 'draft' | 'paid';
};

const props = defineProps<{
    month: string;
    payslips: Slip[];
    accounts: { id: number; name: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Salary sheet', href: payroll() }] },
});

const month = ref(props.month);
const account = ref<number | null>(props.accounts[0]?.id ?? null);
const total = computed(() =>
    props.payslips.reduce((a, p) => a + toPaisa(p.net), 0),
);
const generating = useForm({ month: props.month });

function load(): void {
    router.visit(payroll({ query: { month: month.value } }));
}
function generate(): void {
    generating.month = month.value;
    generating.submit(PayrollController.generate());
}
function adjust(
    p: Slip,
    field: 'bonus' | 'other_deduction',
    value: string,
): void {
    router.visit(PayrollController.update(p.id), {
        data: {
            bonus: field === 'bonus' ? value || '0' : p.bonus,
            other_deduction:
                field === 'other_deduction' ? value || '0' : p.other_deduction,
        },
        preserveScroll: true,
    });
}
function pay(p: Slip): void {
    if (!window.confirm(`Pay ${rs(p.net)} to ${p.staff}?`)) return;
    router.visit(PayrollController.pay(p.id), {
        data: { account_id: account.value },
        preserveScroll: true,
    });
}
function print(p: Slip): void {
    window.open(
        PayrollController.print(p.id).url,
        '_blank',
        'width=380,height=700',
    );
}
</script>

<template>
    <Head title="Salary sheet" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Salary sheet</h1>
                <p class="text-sm text-muted-foreground">
                    Net total {{ rs(total) }}. Paying posts a Salary expense to
                    the cash book.
                </p>
            </div>
            <div class="ml-auto flex flex-wrap items-end gap-2">
                <Input v-model="month" type="month" class="h-10" />
                <Button variant="outline" class="h-10" @click="load"
                    >Show</Button
                >
                <Button
                    class="h-10"
                    :disabled="generating.processing"
                    @click="generate"
                    >{{
                        payslips.length
                            ? 'Recalculate drafts'
                            : 'Make salary sheet'
                    }}</Button
                >
                <select
                    v-model="account"
                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                >
                    <option v-for="a in accounts" :key="a.id" :value="a.id">
                        Pay from {{ a.name }}
                    </option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Staff</th>
                        <th class="p-3 text-center">Days (P/L/Lv/A of W)</th>
                        <th class="p-3 text-right">Earned</th>
                        <th class="p-3 text-right">Advance</th>
                        <th class="p-3 text-right">Bonus</th>
                        <th class="p-3 text-right">Other ded.</th>
                        <th class="p-3 text-right">Net</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payslips" :key="p.id" class="border-t">
                        <td class="p-3">
                            <div class="font-semibold">{{ p.staff }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ p.position }} · {{ rs(p.base_salary) }}/{{
                                    p.salary_type === 'daily' ? 'day' : 'month'
                                }}
                            </div>
                        </td>
                        <td class="p-3 text-center tabular-nums">
                            {{ p.present_days }}/{{ p.late_days }}/{{
                                p.leave_days
                            }}/{{ p.absent_days }} of {{ p.working_days }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ rs(p.earned) }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            −{{ rs(p.advance_deduction) }}
                        </td>
                        <td class="p-3 text-right">
                            <input
                                :value="p.bonus"
                                :disabled="p.status === 'paid'"
                                class="h-9 w-24 rounded border bg-background px-2 text-right tabular-nums"
                                inputmode="decimal"
                                @change="
                                    adjust(
                                        p,
                                        'bonus',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                        </td>
                        <td class="p-3 text-right">
                            <input
                                :value="p.other_deduction"
                                :disabled="p.status === 'paid'"
                                class="h-9 w-24 rounded border bg-background px-2 text-right tabular-nums"
                                inputmode="decimal"
                                @change="
                                    adjust(
                                        p,
                                        'other_deduction',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                        </td>
                        <td class="p-3 text-right font-bold tabular-nums">
                            {{ rs(p.net) }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1.5">
                                <button
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-md border"
                                    aria-label="Print payslip"
                                    @click="print(p)"
                                >
                                    <Printer class="size-3.5" />
                                </button>
                                <button
                                    v-if="p.status === 'draft'"
                                    type="button"
                                    class="h-9 rounded-md border border-foreground px-3 text-xs font-semibold"
                                    @click="pay(p)"
                                >
                                    Pay
                                </button>
                                <span
                                    v-else
                                    class="flex h-9 items-center rounded-md bg-green-100 px-3 text-xs font-bold text-green-900"
                                    >Paid</span
                                >
                            </div>
                        </td>
                    </tr>
                    <tr v-if="payslips.length === 0">
                        <td
                            colspan="8"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No salary sheet for this month yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
