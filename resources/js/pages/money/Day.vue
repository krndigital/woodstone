<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import DayController from '@/actions/App/Http/Controllers/Money/DayController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rs, toPaisa } from '@/lib/money';
import { day } from '@/routes/money';

type Open = {
    business_date: string;
    opening_cash: string;
    opened_by: string;
    opened_at: string;
    expected_cash: string | null;
};
type History = {
    id: number;
    business_date: string;
    opening_cash: string;
    expected_cash: string | null;
    counted_cash: string | null;
    difference: string | null;
    closed_by: string | null;
    note: string | null;
};

const props = defineProps<{
    open: Open | null;
    suggestedOpening: string;
    history: History[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Day open / close', href: day() }] },
});

const openForm = useForm({ opening_cash: props.suggestedOpening, note: '' });
const closeForm = useForm({ counted_cash: '', note: '' });
function openDay(): void {
    openForm.submit(DayController.open(), { preserveScroll: true });
}
function closeDay(): void {
    if (!window.confirm('Close the day with this cash count?')) return;
    closeForm.submit(DayController.close(), {
        preserveScroll: true,
        onSuccess: () => closeForm.reset(),
    });
}
const diff = (): number =>
    toPaisa(closeForm.counted_cash) - toPaisa(props.open?.expected_cash ?? '0');
</script>

<template>
    <Head title="Day open / close" />
    <div class="flex max-w-4xl flex-col gap-4 p-4">
        <h1 class="text-2xl font-bold tracking-tight">Day open / close</h1>

        <section v-if="!open" class="flex flex-col gap-3 rounded-xl border p-5">
            <h2 class="font-bold">Open the day</h2>
            <p class="text-sm text-muted-foreground">
                Count the cash in the drawer before the first sale.
            </p>
            <div class="grid max-w-xs gap-2">
                <Label for="o">Opening cash (Rs)</Label
                ><Input
                    id="o"
                    v-model="openForm.opening_cash"
                    inputmode="decimal"
                    class="h-12 text-lg"
                /><InputError :message="openForm.errors.opening_cash" />
            </div>
            <Button
                class="h-12 w-fit px-8"
                :disabled="openForm.processing"
                @click="openDay"
                >Open day</Button
            >
        </section>

        <section v-else class="flex flex-col gap-3 rounded-xl border p-5">
            <h2 class="font-bold">Day {{ open.business_date }} is open</h2>
            <p class="text-sm text-muted-foreground">
                Opened by {{ open.opened_by }} at
                {{
                    new Date(open.opened_at).toLocaleTimeString('en-GB', {
                        hour: '2-digit',
                        minute: '2-digit',
                    })
                }}
                with {{ rs(open.opening_cash) }}.
            </p>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-muted p-3">
                    <div class="text-xs text-muted-foreground">
                        Cash expected in drawer
                    </div>
                    <div class="text-2xl font-bold tabular-nums">
                        {{ rs(open.expected_cash) }}
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="c">Cash counted (Rs)</Label
                    ><Input
                        id="c"
                        v-model="closeForm.counted_cash"
                        inputmode="decimal"
                        class="h-12 text-lg"
                    /><InputError :message="closeForm.errors.counted_cash" />
                </div>
                <div
                    v-if="closeForm.counted_cash"
                    class="rounded-lg p-3"
                    :class="
                        diff() === 0
                            ? 'bg-green-50 text-green-800 dark:bg-green-950/40 dark:text-green-200'
                            : 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200'
                    "
                >
                    <div class="text-xs">Difference</div>
                    <div class="text-2xl font-bold tabular-nums">
                        {{ diff() > 0 ? '+' : '' }}{{ rs(diff()) }}
                    </div>
                </div>
            </div>
            <Input
                v-model="closeForm.note"
                placeholder="Note (optional, e.g. why short)"
            />
            <Button
                class="h-12 w-fit px-8"
                :disabled="closeForm.processing || !closeForm.counted_cash"
                @click="closeDay"
                >Close day</Button
            >
        </section>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3 text-right">Opening</th>
                        <th class="p-3 text-right">Expected</th>
                        <th class="p-3 text-right">Counted</th>
                        <th class="p-3 text-right">Difference</th>
                        <th class="p-3">Closed by</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="h in history" :key="h.id" class="border-t">
                        <td class="p-3 tabular-nums">
                            {{ h.business_date }}
                            <div
                                v-if="h.note"
                                class="text-xs text-muted-foreground"
                            >
                                {{ h.note }}
                            </div>
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ rs(h.opening_cash) }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ rs(h.expected_cash) }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ rs(h.counted_cash) }}
                        </td>
                        <td
                            class="p-3 text-right font-bold tabular-nums"
                            :class="
                                toPaisa(h.difference) === 0
                                    ? ''
                                    : 'text-red-600'
                            "
                        >
                            {{ rs(h.difference) }}
                        </td>
                        <td class="p-3">{{ h.closed_by }}</td>
                    </tr>
                    <tr v-if="history.length === 0">
                        <td
                            colspan="6"
                            class="p-8 text-center text-muted-foreground"
                        >
                            No closed days yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
