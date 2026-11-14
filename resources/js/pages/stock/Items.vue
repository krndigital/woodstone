<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    History,
    Pencil,
    Plus,
    SlidersHorizontal,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import StockItemController from '@/actions/App/Http/Controllers/Stock/StockItemController';
import InputError from '@/components/InputError.vue';
import StockTabs from '@/components/stock/StockTabs.vue';
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
import { index as stockIndex } from '@/routes/stock/items';

type Item = {
    id: number;
    name: string;
    kind: 'ingredient' | 'resale' | 'asset';
    kind_label: string;
    unit: string;
    purchase_unit: string | null;
    purchase_factor: string | null;
    on_hand: string;
    avg_cost: string;
    value: string;
    reorder_level: string;
    low: boolean;
    is_active: boolean;
};
type Movement = {
    id: number;
    type: string;
    quantity: string;
    unit_cost: string | null;
    reason: string | null;
    by: string;
    created_at: string;
};
type Option = { value: string; label: string };

const props = defineProps<{
    items: Item[];
    kinds: Option[];
    entryTypes: Option[];
    movements?: Movement[];
    expiring: {
        name: string;
        unit: string;
        quantity: string;
        expires_on: string;
    }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stock', href: stockIndex() }] },
});

const q = ref('');
const kind = ref<string>('all');
const onlyLow = ref(false);
const shown = computed(() =>
    props.items.filter(
        (i) =>
            (kind.value === 'all' || i.kind === kind.value) &&
            (!onlyLow.value || i.low) &&
            i.name.toLowerCase().includes(q.value.trim().toLowerCase()),
    ),
);
const lowCount = computed(() => props.items.filter((i) => i.low).length);
const totalValue = computed(() =>
    props.items.reduce((a, i) => a + toPaisa(i.value), 0),
);
const num = (v: string): string => String(Number(v));

const panel = ref<'edit' | 'adjust' | 'history' | null>(null);
const current = ref<Item | null>(null);

const editForm = useForm({
    name: '',
    kind: 'ingredient',
    unit: 'g',
    purchase_unit: '',
    purchase_factor: '',
    reorder_level: '0',
    is_active: true,
});
function openEdit(i: Item | null): void {
    current.value = i;
    editForm.clearErrors();
    editForm.name = i?.name ?? '';
    editForm.kind = i?.kind ?? 'ingredient';
    editForm.unit = i?.unit ?? 'g';
    editForm.purchase_unit = i?.purchase_unit ?? '';
    editForm.purchase_factor = i?.purchase_factor ? num(i.purchase_factor) : '';
    editForm.reorder_level = i ? num(i.reorder_level) : '0';
    editForm.is_active = i?.is_active ?? true;
    panel.value = 'edit';
}
function save(): void {
    editForm.submit(
        current.value
            ? StockItemController.update(current.value.id)
            : StockItemController.store(),
        {
            preserveScroll: true,
            onSuccess: () => (panel.value = null),
        },
    );
}

const adjustForm = useForm({
    type: 'wastage',
    quantity: '',
    unit_cost: '',
    reason: '',
});
function openAdjust(i: Item, type = 'wastage'): void {
    current.value = i;
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.type = type;
    adjustForm.quantity = type === 'count' ? num(i.on_hand) : '';
    panel.value = 'adjust';
}
function adjust(): void {
    if (!current.value) return;
    adjustForm.submit(StockItemController.adjust(current.value.id), {
        preserveScroll: true,
        onSuccess: () => (panel.value = null),
    });
}
const HELP: Record<string, string> = {
    opening:
        'Stock you already have on go-live. Add the cost per unit if you know it.',
    wastage: 'Spoiled, burnt or broken. Reduces stock.',
    staff_meal: 'Used for staff food. Reduces stock.',
    adjustment: 'Correction: use a minus sign to reduce, e.g. -250.',
    count: 'Enter what you counted. The difference is recorded.',
};

function openHistory(i: Item): void {
    current.value = i;
    panel.value = 'history';
    router.reload({ only: ['movements'], data: { item: i.id } });
}
</script>

<template>
    <Head title="Stock" />
    <div class="flex flex-col gap-4 p-4">
        <StockTabs current="items" />
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Stock</h1>
                <p class="text-sm text-muted-foreground">
                    Stock value {{ rs(totalValue) }} at average cost
                </p>
            </div>
            <Button class="ml-auto h-11" @click="openEdit(null)"
                ><Plus class="size-4" /> Add item</Button
            >
        </div>

        <button
            v-if="lowCount"
            type="button"
            class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-left text-sm font-semibold text-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
            @click="onlyLow = !onlyLow"
        >
            <TriangleAlert class="size-4" /> {{ lowCount }} item(s) at or below
            reorder level — {{ onlyLow ? 'show all' : 'show them' }}
        </button>

        <details
            v-if="expiring.length"
            class="rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-900 dark:bg-red-950/40 dark:text-red-100"
        >
            <summary class="cursor-pointer font-semibold">
                {{ expiring.length }} batch(es) expire within 7 days or have
                expired — use these first
            </summary>
            <ul class="mt-2 flex flex-col gap-1">
                <li
                    v-for="(e, i) in expiring"
                    :key="i"
                    class="flex justify-between"
                >
                    <span>{{ e.name }} · {{ e.quantity }} {{ e.unit }}</span
                    ><span class="tabular-nums">{{ e.expires_on }}</span>
                </li>
            </ul>
        </details>

        <div class="flex flex-wrap items-center gap-2">
            <Input v-model="q" class="h-10 w-60" placeholder="Search item" />
            <button
                v-for="k in [{ value: 'all', label: 'All' }, ...kinds]"
                :key="k.value"
                type="button"
                class="h-10 rounded-full border px-4 text-sm font-semibold"
                :class="
                    kind === k.value
                        ? 'border-foreground bg-foreground text-background'
                        : 'hover:bg-muted'
                "
                @click="kind = k.value"
            >
                {{ k.label }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Item</th>
                        <th class="p-3 text-right">On hand</th>
                        <th class="p-3 text-right">Reorder at</th>
                        <th class="p-3 text-right">Avg cost / unit</th>
                        <th class="p-3 text-right">Value</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="i in shown"
                        :key="i.id"
                        class="border-t"
                        :class="i.is_active ? '' : 'opacity-50'"
                    >
                        <td class="p-3">
                            <div class="font-semibold">{{ i.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ i.kind_label
                                }}{{
                                    i.purchase_unit
                                        ? ` · 1 ${i.purchase_unit} = ${num(i.purchase_factor ?? '0')} ${i.unit}`
                                        : ''
                                }}
                            </div>
                        </td>
                        <td
                            class="p-3 text-right font-bold tabular-nums"
                            :class="
                                Number(i.on_hand) < 0
                                    ? 'text-red-600'
                                    : i.low
                                      ? 'text-amber-700 dark:text-amber-400'
                                      : ''
                            "
                        >
                            {{ num(i.on_hand) }} {{ i.unit }}
                        </td>
                        <td
                            class="p-3 text-right text-muted-foreground tabular-nums"
                        >
                            {{ num(i.reorder_level) }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            Rs {{ Number(i.avg_cost).toFixed(2) }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ rs(i.value) }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1.5">
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold"
                                    @click="openAdjust(i, 'count')"
                                >
                                    Count
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold"
                                    @click="openAdjust(i)"
                                >
                                    <SlidersHorizontal class="size-3.5" /> Entry
                                </button>
                                <button
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-md border"
                                    aria-label="History"
                                    @click="openHistory(i)"
                                >
                                    <History class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-md border"
                                    aria-label="Edit"
                                    @click="openEdit(i)"
                                >
                                    <Pencil class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="shown.length === 0">
                        <td
                            colspan="6"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No stock items yet. Add your ingredients and drinks.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Sheet :open="panel !== null" @update:open="(v) => !v && (panel = null)">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <template v-if="panel === 'edit'">
                <SheetHeader
                    ><SheetTitle>{{
                        current ? 'Edit item' : 'Add stock item'
                    }}</SheetTitle
                    ><SheetDescription
                        >Stock is counted in the base unit (g, ml,
                        pcs).</SheetDescription
                    ></SheetHeader
                >
                <form class="flex flex-col gap-3 px-4" @submit.prevent="save">
                    <div class="grid gap-2">
                        <Label for="n">Name</Label
                        ><Input id="n" v-model="editForm.name" /><InputError
                            :message="editForm.errors.name"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Kind</Label>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                v-for="k in kinds"
                                :key="k.value"
                                type="button"
                                class="h-11 rounded-lg border text-xs font-semibold"
                                :class="
                                    editForm.kind === k.value
                                        ? 'border-foreground bg-foreground text-background'
                                        : ''
                                "
                                @click="editForm.kind = k.value"
                            >
                                {{ k.label }}
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="u">Base unit</Label>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="u in [
                                    'g',
                                    'ml',
                                    'pcs',
                                    'kg',
                                    'ltr',
                                    'bottle',
                                    'pack',
                                ]"
                                :key="u"
                                type="button"
                                class="h-9 rounded-md border px-3 text-sm"
                                :class="
                                    editForm.unit === u
                                        ? 'border-foreground bg-foreground text-background'
                                        : ''
                                "
                                @click="editForm.unit = u"
                            >
                                {{ u }}
                            </button>
                        </div>
                        <Input id="u" v-model="editForm.unit" />
                        <InputError :message="editForm.errors.unit" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="grid gap-2">
                            <Label for="pu">Bought in (optional)</Label
                            ><Input
                                id="pu"
                                v-model="editForm.purchase_unit"
                                placeholder="kg / carton / crate"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pf">{{ editForm.unit }} in one</Label
                            ><Input
                                id="pf"
                                v-model="editForm.purchase_factor"
                                inputmode="decimal"
                                placeholder="1000"
                            />
                        </div>
                    </div>
                    <InputError
                        :message="
                            editForm.errors.purchase_unit ||
                            editForm.errors.purchase_factor
                        "
                    />
                    <div class="grid gap-2">
                        <Label for="r"
                            >Reorder when at or below ({{
                                editForm.unit
                            }})</Label
                        ><Input
                            id="r"
                            v-model="editForm.reorder_level"
                            inputmode="decimal"
                        /><InputError
                            :message="editForm.errors.reorder_level"
                        />
                    </div>
                    <label
                        v-if="current"
                        class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="editForm.is_active"
                            type="checkbox"
                            class="size-4"
                        />
                        In use</label
                    >
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="editForm.processing"
                        >Save</Button
                    >
                </form>
            </template>

            <template v-else-if="panel === 'adjust' && current">
                <SheetHeader
                    ><SheetTitle>{{ current.name }}</SheetTitle
                    ><SheetDescription
                        >On hand {{ num(current.on_hand) }}
                        {{ current.unit }}</SheetDescription
                    ></SheetHeader
                >
                <form class="flex flex-col gap-3 px-4" @submit.prevent="adjust">
                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            v-for="t in entryTypes"
                            :key="t.value"
                            type="button"
                            class="h-11 rounded-lg border text-sm font-semibold"
                            :class="
                                adjustForm.type === t.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : ''
                            "
                            @click="adjustForm.type = t.value"
                        >
                            {{ t.label }}
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ HELP[adjustForm.type] }}
                    </p>
                    <div class="grid gap-2">
                        <Label for="aq"
                            >{{
                                adjustForm.type === 'count'
                                    ? 'Counted'
                                    : 'Quantity'
                            }}
                            ({{ current.unit }})</Label
                        ><Input
                            id="aq"
                            v-model="adjustForm.quantity"
                            inputmode="decimal"
                        /><InputError :message="adjustForm.errors.quantity" />
                    </div>
                    <div
                        v-if="adjustForm.type === 'opening'"
                        class="grid gap-2"
                    >
                        <Label for="ac"
                            >Cost per {{ current.unit }} (Rs, optional)</Label
                        ><Input
                            id="ac"
                            v-model="adjustForm.unit_cost"
                            inputmode="decimal"
                        /><InputError :message="adjustForm.errors.unit_cost" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ar">Reason</Label
                        ><Input
                            id="ar"
                            v-model="adjustForm.reason"
                        /><InputError
                            :message="
                                adjustForm.errors.reason ||
                                adjustForm.errors.type
                            "
                        />
                    </div>
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="adjustForm.processing"
                        >Save entry</Button
                    >
                </form>
            </template>

            <template v-else-if="panel === 'history' && current">
                <SheetHeader
                    ><SheetTitle>{{ current.name }} — history</SheetTitle
                    ><SheetDescription
                        >Last 100 movements</SheetDescription
                    ></SheetHeader
                >
                <div class="flex flex-col divide-y px-4 text-sm">
                    <div
                        v-for="m in movements ?? []"
                        :key="m.id"
                        class="flex items-start justify-between gap-2 py-2"
                    >
                        <div>
                            <div class="font-semibold">{{ m.type }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    new Date(m.created_at).toLocaleString(
                                        'en-GB',
                                    )
                                }}
                                · {{ m.by }}
                            </div>
                            <div v-if="m.reason" class="text-xs">
                                {{ m.reason }}
                            </div>
                        </div>
                        <span
                            class="font-bold tabular-nums"
                            :class="
                                Number(m.quantity) < 0
                                    ? 'text-red-600'
                                    : 'text-green-700 dark:text-green-400'
                            "
                            >{{ Number(m.quantity) > 0 ? '+' : ''
                            }}{{ num(m.quantity) }}</span
                        >
                    </div>
                    <p
                        v-if="(movements ?? []).length === 0"
                        class="py-6 text-center text-muted-foreground"
                    >
                        No movements yet.
                    </p>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
