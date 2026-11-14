<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, FileSpreadsheet, FileText } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as reportsIndex } from '@/routes/reports';

type Column = {
    key: string;
    label: string;
    type: 'text' | 'int' | 'money' | 'percent' | 'qty';
};
type Row = Record<string, string | number | null>;

const props = defineProps<{
    report: { key: string; title: string; description: string };
    filters: { from: string; to: string; bs: string };
    data: {
        columns: Column[];
        rows: Row[];
        totals: Row | null;
        notes: string[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reports', href: reportsIndex() }] },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const sortKey = ref<string | null>(null);
const sortDir = ref<1 | -1>(-1);

const iso = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
function preset(name: string): void {
    const now = new Date();
    const d = (y: number, m: number, day: number): Date => new Date(y, m, day);
    const y = now.getFullYear();
    const m = now.getMonth();
    const ranges: Record<string, [Date, Date]> = {
        today: [now, now],
        yesterday: [d(y, m, now.getDate() - 1), d(y, m, now.getDate() - 1)],
        week: [d(y, m, now.getDate() - now.getDay()), now],
        month: [d(y, m, 1), now],
        last_month: [d(y, m - 1, 1), d(y, m, 0)],
        quarter: [d(y, Math.floor(m / 3) * 3, 1), now],
    };
    [from.value, to.value] = ranges[name].map(iso) as [string, string];
    load();
}
function load(): void {
    router.visit(
        ReportController.show(props.report.key, {
            query: { from: from.value, to: to.value },
        }),
        { preserveScroll: true },
    );
}
const query = computed(() => ({
    query: { from: props.filters.from, to: props.filters.to },
}));

function fmt(
    v: string | number | null | undefined,
    type: Column['type'],
): string {
    if (typeof v !== 'number') return v ?? '';
    if (type === 'money')
        return (v / 100).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    if (type === 'percent') return `${(v / 100).toFixed(1)}%`;
    if (type === 'qty') return String(Number((v / 1000).toFixed(3)));
    return v.toLocaleString('en-IN');
}
function sortBy(key: string): void {
    if (sortKey.value === key) sortDir.value = sortDir.value === 1 ? -1 : 1;
    else {
        sortKey.value = key;
        sortDir.value = -1;
    }
}
const rows = computed(() => {
    if (sortKey.value === null) return props.data.rows;
    const k = sortKey.value;
    return [...props.data.rows].sort((a, b) => {
        const x = a[k] ?? '';
        const y = b[k] ?? '';
        return (
            (typeof x === 'number' && typeof y === 'number'
                ? x - y
                : String(x).localeCompare(String(y))) * sortDir.value
        );
    });
});
</script>

<template>
    <Head :title="report.title" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <Link
                :href="reportsIndex().url"
                class="flex size-11 items-center justify-center rounded-lg border"
                aria-label="Back to reports"
                ><ArrowLeft class="size-5"
            /></Link>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    {{ report.title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ report.description }}
                </p>
            </div>
            <div class="ml-auto flex gap-2">
                <a
                    :href="ReportController.excel(report.key, query).url"
                    class="flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-semibold"
                    ><FileSpreadsheet class="size-4" /> Excel</a
                >
                <a
                    :href="ReportController.pdf(report.key, query).url"
                    target="_blank"
                    class="flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-semibold"
                    ><FileText class="size-4" /> PDF</a
                >
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <button
                v-for="p in [
                    ['today', 'Today'],
                    ['yesterday', 'Yesterday'],
                    ['week', 'This week'],
                    ['month', 'This month'],
                    ['last_month', 'Last month'],
                    ['quarter', 'Quarter'],
                ]"
                :key="p[0]"
                type="button"
                class="h-10 rounded-full border px-4 text-sm font-semibold hover:bg-muted"
                @click="preset(p[0])"
            >
                {{ p[1] }}
            </button>
            <Input v-model="from" type="date" class="h-10 w-40" />
            <Input v-model="to" type="date" class="h-10 w-40" />
            <Button variant="outline" class="h-10" @click="load">Show</Button>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th
                            v-for="c in data.columns"
                            :key="c.key"
                            class="cursor-pointer p-3 whitespace-nowrap select-none"
                            :class="c.type === 'text' ? '' : 'text-right'"
                            @click="sortBy(c.key)"
                        >
                            {{ c.label
                            }}<span v-if="sortKey === c.key">{{
                                sortDir === 1 ? ' ▲' : ' ▼'
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in rows" :key="i" class="border-t">
                        <td
                            v-for="c in data.columns"
                            :key="c.key"
                            class="p-3"
                            :class="[
                                c.type === 'text'
                                    ? ''
                                    : 'text-right tabular-nums',
                                typeof r[c.key] === 'number' &&
                                (r[c.key] as number) < 0
                                    ? 'text-red-600'
                                    : '',
                            ]"
                        >
                            {{ fmt(r[c.key], c.type) }}
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td
                            :colspan="data.columns.length"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No data for this period.
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="data.totals && rows.length">
                    <tr class="border-t-2 bg-muted/50 font-bold">
                        <td
                            v-for="c in data.columns"
                            :key="c.key"
                            class="p-3"
                            :class="
                                c.type === 'text'
                                    ? ''
                                    : 'text-right tabular-nums'
                            "
                        >
                            {{
                                c.key in data.totals
                                    ? fmt(data.totals[c.key], c.type)
                                    : ''
                            }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p
            v-for="n in data.notes"
            :key="n"
            class="text-xs text-muted-foreground"
        >
            {{ n }}
        </p>
    </div>
</template>
