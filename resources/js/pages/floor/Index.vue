<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, RefreshCw } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import FloorController from '@/actions/App/Http/Controllers/Floor/FloorController';
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
import { index as floorIndex, qrCards } from '@/routes/floor';
import { index as reservationsIndex } from '@/routes/floor/reservations';

type Zone = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
};
type Table = {
    id: number;
    zone_id: number;
    code: string;
    label: string;
    kind: 'table' | 'cabin';
    seats: number;
    min_spend: string;
    status: 'free' | 'occupied' | 'bill' | 'reserved' | 'cleaning';
    status_label: string;
    reserved_for: string | null;
    reserved_at: string | null;
    status_changed_at: string | null;
    sort_order: number;
    is_active: boolean;
};

const props = defineProps<{
    zones: Zone[];
    tables: Table[];
    can: { manage: boolean; pos: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tables', href: floorIndex() }] },
});

const STYLE: Record<Table['status'], string> = {
    free: 'border-green-300 bg-green-50 text-green-800',
    occupied: 'border-orange-300 bg-orange-50 text-orange-800',
    bill: 'border-blue-300 bg-blue-50 text-blue-800',
    reserved: 'border-violet-300 bg-violet-50 text-violet-800',
    cleaning: 'border-slate-300 bg-slate-100 text-slate-700',
};

const tab = ref<'floor' | 'setup'>('floor');
const filter = ref<Table['status'] | 'all'>('all');
const selectedId = ref<number | null>(null);
const selected = computed(
    () => props.tables.find((t) => t.id === selectedId.value) ?? null,
);

const counts = computed(() => {
    const c: Record<string, number> = { all: 0 };
    props.tables
        .filter((t) => t.is_active)
        .forEach((t) => {
            c.all++;
            c[t.status] = (c[t.status] ?? 0) + 1;
        });
    return c;
});

function tablesIn(zone: Zone): Table[] {
    return props.tables.filter((t) => t.zone_id === zone.id && t.is_active);
}

function minutes(iso: string | null): string {
    if (iso === null) return '';
    const m = Math.max(
        0,
        Math.round((Date.now() - new Date(iso).getTime()) / 60000),
    );
    return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h ${m % 60} min`;
}

/* auto refresh so the floor stays live */
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    timer = setInterval(() => {
        if (tab.value === 'floor' && !document.hidden) {
            router.reload({ only: ['tables'] });
        }
    }, 15000);
});
onBeforeUnmount(() => clearInterval(timer));

/* status changes */
const reserveForm = useForm({
    status: 'reserved',
    reserved_for: '',
    reserved_at: '',
});

function setFree(t: Table): void {
    router.visit(FloorController.updateStatus(t.id), {
        data: { status: 'free' },
        preserveScroll: true,
    });
}

function reserve(): void {
    if (selected.value === null) return;
    reserveForm.submit(FloorController.updateStatus(selected.value.id), {
        preserveScroll: true,
        onSuccess: () => reserveForm.reset(),
    });
}

/* setup */
const zoneForm = useForm({ name: '', sort_order: 0, is_active: true });
const zoneEditing = ref<Zone | null>(null);
function editZone(z: Zone | null): void {
    zoneEditing.value = z;
    zoneForm.clearErrors();
    zoneForm.name = z?.name ?? '';
    zoneForm.sort_order = z?.sort_order ?? 0;
    zoneForm.is_active = z?.is_active ?? true;
}
function saveZone(): void {
    zoneForm.submit(
        zoneEditing.value
            ? FloorController.updateZone(zoneEditing.value.id)
            : FloorController.storeZone(),
        { preserveScroll: true, onSuccess: () => editZone(null) },
    );
}

const tableForm = useForm({
    zone_id: null as number | null,
    code: '',
    kind: 'table' as 'table' | 'cabin',
    seats: 4,
    min_spend: '',
    sort_order: 0,
    is_active: true,
});
const tableEditing = ref<Table | null>(null);
function editTable(t: Table | null): void {
    tableEditing.value = t;
    tableForm.clearErrors();
    tableForm.zone_id = t?.zone_id ?? props.zones[0]?.id ?? null;
    tableForm.code = t?.code ?? '';
    tableForm.kind = t?.kind ?? 'table';
    tableForm.seats = t?.seats ?? 4;
    tableForm.min_spend = t && Number(t.min_spend) > 0 ? t.min_spend : '';
    tableForm.sort_order = t?.sort_order ?? 0;
    tableForm.is_active = t?.is_active ?? true;
}
// A zone added while the table form is open becomes its zone straight away.
watch(
    () => props.zones.length,
    () => {
        if (tableForm.zone_id === null && props.zones.length > 0) {
            tableForm.zone_id = props.zones[0].id;
        }
    },
);
function saveTable(): void {
    tableForm.submit(
        tableEditing.value
            ? FloorController.updateTable(tableEditing.value.id)
            : FloorController.storeTable(),
        { preserveScroll: true, onSuccess: () => editTable(null) },
    );
}
</script>

<template>
    <Head title="Tables" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Tables & cabins
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ (counts.occupied ?? 0) + (counts.bill ?? 0) }} of
                    {{ counts.all }} in use · updates every 15 seconds
                </p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-10"
                    @click="router.reload({ only: ['tables'] })"
                >
                    <RefreshCw class="size-4" /> Refresh
                </Button>
                <Link
                    :href="reservationsIndex().url"
                    class="flex h-10 items-center rounded-md border px-3 text-sm font-semibold"
                    >Bookings</Link
                >
                <Link
                    v-if="can.manage"
                    :href="qrCards().url"
                    class="flex h-10 items-center rounded-md border px-3 text-sm font-semibold"
                    >QR cards</Link
                >
                <div v-if="can.manage" class="flex rounded-lg bg-muted p-1">
                    <button
                        type="button"
                        class="h-9 rounded-md px-4 text-sm font-semibold"
                        :class="
                            tab === 'floor'
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="tab = 'floor'"
                    >
                        Floor
                    </button>
                    <button
                        type="button"
                        class="h-9 rounded-md px-4 text-sm font-semibold"
                        :class="
                            tab === 'setup'
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="tab = 'setup'"
                    >
                        Setup
                    </button>
                </div>
            </div>
        </div>

        <template v-if="tab === 'floor'">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="f in [
                        'all',
                        'free',
                        'occupied',
                        'bill',
                        'reserved',
                        'cleaning',
                    ] as const"
                    :key="f"
                    type="button"
                    class="h-10 rounded-full border px-4 text-sm font-semibold"
                    :class="
                        filter === f
                            ? 'border-foreground bg-foreground text-background'
                            : 'hover:bg-muted'
                    "
                    @click="filter = f"
                >
                    {{
                        f === 'all'
                            ? 'All'
                            : f === 'bill'
                              ? 'Bill asked'
                              : f === 'cleaning'
                                ? 'Needs cleaning'
                                : f.charAt(0).toUpperCase() + f.slice(1)
                    }}
                    <span class="ml-1 text-xs opacity-70">{{
                        counts[f] ?? 0
                    }}</span>
                </button>
            </div>

            <section
                v-for="zone in zones.filter((z) => z.is_active)"
                :key="zone.id"
                class="rounded-2xl border p-4"
            >
                <h2 class="mb-3 font-bold">{{ zone.name }}</h2>
                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6"
                >
                    <button
                        v-for="t in tablesIn(zone)"
                        :key="t.id"
                        type="button"
                        class="flex min-h-28 flex-col items-start gap-1 rounded-2xl border-2 p-3 text-left transition hover:-translate-y-0.5"
                        :class="[
                            STYLE[t.status],
                            {
                                'opacity-30':
                                    filter !== 'all' && filter !== t.status,
                                'ring-4 ring-foreground/70':
                                    selectedId === t.id,
                            },
                        ]"
                        :aria-label="`${t.label}, ${t.status_label}`"
                        @click="selectedId = t.id"
                    >
                        <span class="text-xl font-bold text-foreground">{{
                            t.code
                        }}</span>
                        <span class="text-xs font-semibold">{{
                            t.status_label
                        }}</span>
                        <span class="mt-auto text-xs text-muted-foreground">
                            {{
                                t.status === 'reserved'
                                    ? t.reserved_for
                                    : t.status === 'free'
                                      ? `${t.seats} seats`
                                      : minutes(t.status_changed_at)
                            }}
                        </span>
                    </button>
                </div>
            </section>
        </template>

        <div v-else class="grid gap-6 lg:grid-cols-2">
            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <h2 class="text-lg font-bold">Zones</h2>
                <form
                    class="flex flex-wrap items-end gap-2"
                    @submit.prevent="saveZone"
                >
                    <div class="grid gap-1">
                        <Label for="z-name">{{
                            zoneEditing ? 'Rename zone' : 'New zone'
                        }}</Label>
                        <Input
                            id="z-name"
                            v-model="zoneForm.name"
                            class="h-10 w-48"
                            required
                        />
                    </div>
                    <label
                        v-if="zoneEditing"
                        class="flex h-10 items-center gap-2 text-sm"
                        ><input
                            v-model="zoneForm.is_active"
                            type="checkbox"
                            class="size-4"
                        />
                        In use</label
                    >
                    <Button
                        type="submit"
                        class="h-10"
                        :disabled="zoneForm.processing"
                        >{{ zoneEditing ? 'Save' : 'Add' }}</Button
                    >
                    <Button
                        v-if="zoneEditing"
                        type="button"
                        variant="ghost"
                        class="h-10"
                        @click="editZone(null)"
                        >Cancel</Button
                    >
                </form>
                <InputError
                    :message="zoneForm.errors.name || zoneForm.errors.is_active"
                />
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="z in zones"
                        :key="z.id"
                        class="flex items-center gap-2 px-3 py-2 text-sm"
                        :class="{ 'opacity-50': !z.is_active }"
                    >
                        <span class="font-semibold">{{ z.name }}</span>
                        <span class="ml-auto text-xs text-muted-foreground"
                            >{{
                                tables.filter((t) => t.zone_id === z.id).length
                            }}
                            tables</span
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            :aria-label="`Edit ${z.name}`"
                            @click="editZone(z)"
                            ><Pencil class="size-4"
                        /></Button>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <div class="flex items-center">
                    <h2 class="text-lg font-bold">Tables & cabins</h2>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="ml-auto"
                        @click="editTable(null)"
                        ><Plus class="size-4" /> New</Button
                    >
                </div>
                <form
                    class="grid grid-cols-2 gap-2 sm:grid-cols-3"
                    @submit.prevent="saveTable"
                >
                    <div class="grid gap-1">
                        <Label for="t-code">Code</Label>
                        <Input
                            id="t-code"
                            v-model="tableForm.code"
                            class="h-10 uppercase"
                            required
                            placeholder="G9"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label for="t-zone">Zone</Label>
                        <select
                            id="t-zone"
                            v-model="tableForm.zone_id"
                            class="h-10 rounded-md border bg-background px-2 text-sm"
                            required
                        >
                            <option
                                v-if="zones.length === 0"
                                :value="null"
                                disabled
                            >
                                Add a zone first (left)
                            </option>
                            <option
                                v-for="z in zones"
                                :key="z.id"
                                :value="z.id"
                            >
                                {{ z.name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="t-kind">Type</Label>
                        <select
                            id="t-kind"
                            v-model="tableForm.kind"
                            class="h-10 rounded-md border bg-background px-2 text-sm"
                        >
                            <option value="table">Table</option>
                            <option value="cabin">Wooden cabin</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="t-seats">Seats</Label>
                        <Input
                            id="t-seats"
                            v-model.number="tableForm.seats"
                            type="number"
                            min="1"
                            max="40"
                            class="h-10"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label for="t-min">Minimum spend (Rs)</Label>
                        <Input
                            id="t-min"
                            v-model="tableForm.min_spend"
                            inputmode="decimal"
                            placeholder="0"
                            class="h-10"
                        />
                    </div>
                    <label
                        v-if="tableEditing"
                        class="flex h-10 items-center gap-2 self-end text-sm"
                        ><input
                            v-model="tableForm.is_active"
                            type="checkbox"
                            class="size-4"
                        />
                        In use</label
                    >
                    <div class="flex items-end gap-2">
                        <Button
                            type="submit"
                            class="h-10"
                            :disabled="
                                tableForm.processing || zones.length === 0
                            "
                            >{{ tableEditing ? 'Save' : 'Add table' }}</Button
                        >
                    </div>
                </form>
                <InputError
                    :message="
                        tableForm.errors.code ||
                        tableForm.errors.zone_id ||
                        tableForm.errors.kind ||
                        tableForm.errors.seats ||
                        tableForm.errors.is_active
                    "
                />
                <ul
                    class="max-h-[420px] divide-y overflow-auto rounded-lg border"
                >
                    <li
                        v-for="t in tables"
                        :key="t.id"
                        class="flex items-center gap-2 px-3 py-2 text-sm"
                        :class="{ 'opacity-50': !t.is_active }"
                    >
                        <span class="w-12 font-bold">{{ t.code }}</span>
                        <span class="text-muted-foreground"
                            >{{ zones.find((z) => z.id === t.zone_id)?.name }} ·
                            {{ t.kind === 'cabin' ? 'Cabin' : 'Table' }} ·
                            {{ t.seats }} seats</span
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="ml-auto"
                            :aria-label="`Edit ${t.code}`"
                            @click="editTable(t)"
                            ><Pencil class="size-4"
                        /></Button>
                    </li>
                </ul>
            </section>
        </div>
    </div>

    <Sheet
        :open="selected !== null"
        @update:open="
            (v: boolean) => {
                if (!v) selectedId = null;
            }
        "
    >
        <SheetContent class="w-full sm:max-w-md">
            <template v-if="selected">
                <SheetHeader>
                    <SheetTitle>{{ selected.label }}</SheetTitle>
                    <SheetDescription
                        >{{
                            zones.find((z) => z.id === selected?.zone_id)?.name
                        }}
                        · {{ selected.seats }} seats ·
                        {{ selected.status_label }}</SheetDescription
                    >
                </SheetHeader>
                <div class="flex flex-col gap-4 px-4 pb-6">
                    <div
                        v-if="selected.status === 'reserved'"
                        class="rounded-xl bg-violet-50 p-3 text-sm text-violet-900"
                    >
                        Reserved for <b>{{ selected.reserved_for }}</b>
                        <span v-if="selected.reserved_at">
                            ·
                            {{
                                new Date(selected.reserved_at).toLocaleString(
                                    'en-GB',
                                    {
                                        day: 'numeric',
                                        month: 'short',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    },
                                )
                            }}</span
                        >
                    </div>

                    <form
                        v-if="selected.status === 'free'"
                        class="flex flex-col gap-3 rounded-xl border p-3"
                        @submit.prevent="reserve"
                    >
                        <b class="text-sm">Reserve this table</b>
                        <Input
                            v-model="reserveForm.reserved_for"
                            placeholder="Guest name and phone"
                            required
                        />
                        <InputError
                            :message="reserveForm.errors.reserved_for"
                        />
                        <Input
                            v-model="reserveForm.reserved_at"
                            type="datetime-local"
                            required
                        />
                        <InputError
                            :message="
                                reserveForm.errors.reserved_at ||
                                reserveForm.errors.status
                            "
                        />
                        <Button type="submit" :disabled="reserveForm.processing"
                            >Reserve</Button
                        >
                    </form>

                    <Button
                        v-if="selected.status === 'reserved'"
                        variant="outline"
                        class="h-11"
                        @click="setFree(selected)"
                        >Cancel reservation</Button
                    >
                    <Button
                        v-if="selected.status === 'cleaning'"
                        class="h-11"
                        @click="setFree(selected)"
                        >Mark as clean</Button
                    >
                    <p
                        v-if="
                            selected.status === 'occupied' ||
                            selected.status === 'bill'
                        "
                        class="rounded-xl bg-muted p-3 text-sm text-muted-foreground"
                    >
                        Guests are seated. Open the order from the POS to add
                        items, move the table or take payment.
                    </p>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
