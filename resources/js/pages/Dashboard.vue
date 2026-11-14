<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    ChefHat,
    Clock,
    ReceiptText,
    RefreshCw,
    ShieldCheck,
    ShoppingCart,
    Users,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import PosController from '@/actions/App/Http/Controllers/Pos/PosController';
import { usePermissions } from '@/composables/usePermissions';
import { minutesSince, rs } from '@/lib/money';
import { dashboard } from '@/routes';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as billingIndex } from '@/routes/billing';
import { index as kitchenIndex } from '@/routes/kitchen';
import { day } from '@/routes/money';
import { index as posIndex } from '@/routes/pos';
import { index as reportsIndex } from '@/routes/reports';
import { edit as editSecurity } from '@/routes/security';
import { index as usersIndex } from '@/routes/users';

type Owner = {
    range: 'today' | 'month';
    kpis: {
        sales: number;
        bills: number;
        average: number;
        covers: number;
        discount: number;
        due: number;
        cash: number;
        expenses: number;
        profit: number;
    };
    mix: Record<string, number>;
    topItems: { name: string; qty: number; revenue: number }[];
    trend: { date: string; sales: number }[];
    running: {
        id: number;
        label: string;
        waiter: string;
        subtotal: string;
        items_count: number;
        opened_at: string;
        guests: number;
    }[];
    openCount: number;
};

const props = defineProps<{ owner: Owner | null }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { can } = usePermissions();

const maxTrend = computed(() =>
    Math.max(1, ...(props.owner?.trend.map((t) => t.sales) ?? [1])),
);
const mixTotal = computed(() =>
    Object.values(props.owner?.mix ?? {}).reduce((a, b) => a + b, 0),
);
const MIX: Record<string, { label: string; color: string }> = {
    cash: { label: 'Cash', color: 'bg-emerald-500' },
    qr: { label: 'QR', color: 'bg-sky-500' },
    card: { label: 'Card', color: 'bg-violet-500' },
    due: { label: 'On due', color: 'bg-amber-500' },
};

function setRange(range: 'today' | 'month'): void {
    router.visit(dashboard({ query: { range } }), { preserveScroll: true });
}

let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    if (props.owner)
        timer = setInterval(
            () => !document.hidden && router.reload({ only: ['owner'] }),
            30000,
        );
});
onBeforeUnmount(() => clearInterval(timer));

const quick = computed(() =>
    [
        {
            show: can('pos.use'),
            href: posIndex(),
            icon: ShoppingCart,
            title: 'POS',
            text: 'Open tables and take orders.',
        },
        {
            show: can('kitchen.view'),
            href: kitchenIndex(),
            icon: ChefHat,
            title: 'Kitchen display',
            text: 'See and bump tickets.',
        },
        {
            show: can('billing.take_payment'),
            href: billingIndex(),
            icon: ReceiptText,
            title: 'Bills',
            text: 'Reprint or return bills.',
        },
        {
            show: can('cash.day'),
            href: day(),
            icon: CalendarCheck,
            title: 'Day open / close',
            text: 'Count the cash drawer.',
        },
        {
            show: can('staff.attendance'),
            href: attendanceIndex(),
            icon: Clock,
            title: 'Attendance',
            text: 'Staff check in and out.',
        },
        {
            show: can('users.manage'),
            href: usersIndex(),
            icon: Users,
            title: 'Users & logins',
            text: 'Roles, passwords and PINs.',
        },
        {
            show: true,
            href: editSecurity(),
            icon: ShieldCheck,
            title: 'My password & PIN',
            text: 'Change your password or PIN.',
        },
    ].filter((q) => q.show),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Welcome, {{ user.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Signed in as {{ user.role_label }}.
                </p>
            </div>
            <div v-if="owner" class="ml-auto flex items-center gap-2">
                <div class="flex rounded-lg bg-muted p-1">
                    <button
                        v-for="r in ['today', 'month'] as const"
                        :key="r"
                        type="button"
                        class="h-9 rounded-md px-4 text-sm font-semibold"
                        :class="
                            owner.range === r
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="setRange(r)"
                    >
                        {{ r === 'today' ? 'Today' : 'This month' }}
                    </button>
                </div>
                <button
                    type="button"
                    class="flex size-10 items-center justify-center rounded-md border"
                    aria-label="Refresh"
                    @click="router.reload({ only: ['owner'] })"
                >
                    <RefreshCw class="size-4" />
                </button>
            </div>
        </div>

        <template v-if="owner">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <div class="text-xs text-muted-foreground">Net sales</div>
                    <div class="text-2xl font-bold tabular-nums">
                        {{ rs(owner.kpis.sales) }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ owner.kpis.bills }} bills · avg
                        {{ rs(owner.kpis.average) }}
                    </div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-xs text-muted-foreground">
                        Profit estimate
                    </div>
                    <div
                        class="text-2xl font-bold tabular-nums"
                        :class="
                            owner.kpis.profit < 0
                                ? 'text-red-600'
                                : 'text-green-700 dark:text-green-400'
                        "
                    >
                        {{ rs(owner.kpis.profit) }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        after food cost and expenses
                    </div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-xs text-muted-foreground">
                        Cash in hand
                    </div>
                    <div class="text-2xl font-bold tabular-nums">
                        {{ rs(owner.kpis.cash) }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        from the cash book
                    </div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-xs text-muted-foreground">Expenses</div>
                    <div class="text-2xl font-bold tabular-nums">
                        {{ rs(owner.kpis.expenses) }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        discount {{ rs(owner.kpis.discount) }} · on due
                        {{ rs(owner.kpis.due) }}
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <section class="flex flex-col gap-3 rounded-xl border p-4">
                    <h2 class="font-bold">
                        Sales
                        {{
                            owner.range === 'month'
                                ? 'this month'
                                : 'last 14 days'
                        }}
                    </h2>
                    <div
                        class="flex h-44 items-end gap-1"
                        role="img"
                        aria-label="Sales per day"
                    >
                        <div
                            v-for="t in owner.trend"
                            :key="t.date"
                            class="group relative flex flex-1 flex-col items-center justify-end"
                        >
                            <div
                                class="w-full rounded-t bg-orange-500/80 transition group-hover:bg-orange-600"
                                :style="{
                                    height: `${Math.max(2, (t.sales / maxTrend) * 160)}px`,
                                }"
                            />
                            <span
                                class="pointer-events-none absolute -top-6 hidden rounded bg-foreground px-1.5 py-0.5 text-[10px] whitespace-nowrap text-background group-hover:block"
                                >{{ t.date.slice(5) }} · {{ rs(t.sales) }}</span
                            >
                        </div>
                    </div>
                    <div
                        class="flex justify-between text-[10px] text-muted-foreground"
                    >
                        <span>{{ owner.trend[0]?.date.slice(5) }}</span
                        ><span>{{
                            owner.trend[owner.trend.length - 1]?.date.slice(5)
                        }}</span>
                    </div>

                    <h2 class="mt-2 font-bold">Payment mix</h2>
                    <div
                        v-if="mixTotal"
                        class="flex h-4 overflow-hidden rounded-full"
                    >
                        <div
                            v-for="(v, k) in owner.mix"
                            :key="k"
                            :class="MIX[k]?.color ?? 'bg-slate-400'"
                            :style="{ width: `${(v / mixTotal) * 100}%` }"
                        />
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <span
                            v-for="(v, k) in owner.mix"
                            :key="k"
                            class="flex items-center gap-1"
                            ><span
                                class="size-2.5 rounded-sm"
                                :class="MIX[k]?.color ?? 'bg-slate-400'"
                            />{{ MIX[k]?.label ?? k }} {{ rs(v) }}</span
                        >
                        <span v-if="!mixTotal" class="text-muted-foreground"
                            >No payments yet.</span
                        >
                    </div>
                </section>

                <section class="flex flex-col gap-2 rounded-xl border p-4">
                    <h2 class="font-bold">Top 5 items</h2>
                    <div
                        v-for="(i, n) in owner.topItems"
                        :key="i.name"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span
                            class="flex size-6 items-center justify-center rounded-full bg-muted text-xs font-bold"
                            >{{ n + 1 }}</span
                        >
                        <span class="flex-1 truncate">{{ i.name }}</span>
                        <span class="text-xs text-muted-foreground tabular-nums"
                            >{{ i.qty }} sold</span
                        >
                        <span
                            class="w-24 text-right font-semibold tabular-nums"
                            >{{ rs(i.revenue) }}</span
                        >
                    </div>
                    <p
                        v-if="owner.topItems.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Nothing sold yet.
                    </p>
                    <Link
                        v-if="can('reports.view')"
                        :href="reportsIndex().url"
                        class="mt-auto text-sm font-semibold text-orange-700 hover:underline dark:text-orange-400"
                        >All reports →</Link
                    >
                </section>
            </div>

            <section class="flex flex-col gap-2">
                <h2 class="font-bold">
                    Running tables ({{ owner.openCount }})
                </h2>
                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
                >
                    <Link
                        v-for="o in owner.running"
                        :key="o.id"
                        :href="PosController.show(o.id).url"
                        class="flex flex-col gap-0.5 rounded-xl border-2 border-orange-300 bg-orange-50 p-3 text-orange-900 dark:bg-orange-950/40 dark:text-orange-100"
                    >
                        <span class="font-bold">{{ o.label }}</span>
                        <span class="text-sm font-semibold tabular-nums">{{
                            rs(o.subtotal)
                        }}</span>
                        <span class="text-xs opacity-80"
                            >{{ o.waiter }} · {{ o.guests }} guests ·
                            {{ minutesSince(o.opened_at) }}</span
                        >
                    </Link>
                    <p
                        v-if="owner.running.length === 0"
                        class="col-span-full text-sm text-muted-foreground"
                    >
                        No tables running right now.
                    </p>
                </div>
            </section>
        </template>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="q in quick"
                :key="q.title"
                :href="q.href"
                class="flex flex-col gap-2 rounded-xl border p-5 transition hover:border-primary hover:bg-primary/5"
            >
                <component :is="q.icon" class="size-6 text-primary" />
                <span class="font-semibold">{{ q.title }}</span>
                <span class="text-sm text-muted-foreground">{{ q.text }}</span>
            </Link>
        </div>
    </div>
</template>
