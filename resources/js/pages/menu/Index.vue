<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ImagePlus,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MenuCategoryController from '@/actions/App/Http/Controllers/Menu/MenuCategoryController';
import MenuItemController from '@/actions/App/Http/Controllers/Menu/MenuItemController';
import StationController from '@/actions/App/Http/Controllers/Menu/StationController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { index as menuIndex } from '@/routes/menu';

type Modifier = { name: string; price: string };
type Item = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    category_id: number;
    category: string | null;
    station_id: number;
    station: string | null;
    price: string;
    cost: string | null;
    margin: number | null;
    is_veg: boolean;
    vat_applicable: boolean;
    show_on_qr: boolean;
    is_available: boolean;
    is_active: boolean;
    orderable_now: boolean;
    available_from: string | null;
    available_to: string | null;
    image_url: string | null;
    sort_order: number;
    modifiers: Modifier[];
};
type Category = {
    id: number;
    name: string;
    parent_id: number | null;
    sort_order: number;
    is_active: boolean;
    items_count: number;
};
type Station = {
    id: number;
    name: string;
    kind: string;
    sort_order: number;
    is_active: boolean;
    items_count: number;
};
type Option = { value: string; label: string };

const props = defineProps<{
    items: Item[];
    categories: Category[];
    stations: Station[];
    stationKinds: Option[];
    can: { manage: boolean; availability: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Menu', href: menuIndex() }] },
});

/* ---------- money helpers (display only; the server recomputes everything) ---------- */
const rs = (v: string | null): string =>
    v === null
        ? '—'
        : 'Rs ' +
          Number(v).toLocaleString('en-IN', {
              minimumFractionDigits: 0,
              maximumFractionDigits: 2,
          });

function toPaisa(v: string): number | null {
    const m = /^(\d{1,7})(?:\.(\d{1,2}))?$/.exec(v.trim());
    return m ? Number(m[1]) * 100 + Number((m[2] ?? '0').padEnd(2, '0')) : null;
}

/* ---------- filters (client side; a menu is small) ---------- */
const tab = ref<'items' | 'setup'>('items');
const search = ref('');
const cat = ref<number | 'all'>('all');
const show = ref<'active' | 'soldout' | 'archived' | 'all'>('active');

const topCategories = computed(() =>
    props.categories.filter((c) => c.parent_id === null),
);
const categoryName = (id: number | null): string =>
    props.categories.find((c) => c.id === id)?.name ?? '';

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.items.filter((i) => {
        if (show.value === 'active' && !i.is_active) return false;
        if (show.value === 'soldout' && (!i.is_active || i.is_available))
            return false;
        if (show.value === 'archived' && i.is_active) return false;
        if (cat.value !== 'all') {
            const sub = props.categories.find((c) => c.id === i.category_id);
            if (i.category_id !== cat.value && sub?.parent_id !== cat.value)
                return false;
        }
        return (
            q === '' ||
            i.name.toLowerCase().includes(q) ||
            i.code.toLowerCase().includes(q)
        );
    });
});

const counts = computed(() => ({
    active: props.items.filter((i) => i.is_active).length,
    soldout: props.items.filter((i) => i.is_active && !i.is_available).length,
    archived: props.items.filter((i) => !i.is_active).length,
}));

type ShowFilter = 'active' | 'soldout' | 'archived' | 'all';

const showOptions = computed<{ value: ShowFilter; label: string }[]>(() => {
    const base: { value: ShowFilter; label: string }[] = [
        { value: 'active', label: `On menu (${counts.value.active})` },
        { value: 'soldout', label: `Sold out (${counts.value.soldout})` },
    ];
    return props.can.manage
        ? [
              ...base,
              {
                  value: 'archived',
                  label: `Archived (${counts.value.archived})`,
              },
          ]
        : base;
});

/* ---------- item drawer ---------- */
const open = ref(false);
const editing = ref<Item | null>(null);
const form = useForm({
    code: '',
    name: '',
    description: '',
    category_id: null as number | null,
    station_id: null as number | null,
    price: '',
    cost: '',
    is_veg: false,
    vat_applicable: true,
    show_on_qr: true,
    available_from: '',
    available_to: '',
    sort_order: 0,
    modifiers: [] as Modifier[],
});

const liveMargin = computed(() => {
    const p = toPaisa(form.price);
    const c = toPaisa(form.cost);
    if (p === null || p === 0 || c === null) return null;
    return { profit: (p - c) / 100, percent: Math.floor(((p - c) * 100) / p) };
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.category_id =
        cat.value === 'all' ? (topCategories.value[0]?.id ?? null) : cat.value;
    form.station_id = props.stations.find((s) => s.is_active)?.id ?? null;
    open.value = true;
}

function openEdit(item: Item): void {
    editing.value = item;
    form.clearErrors();
    Object.assign(form, {
        code: item.code,
        name: item.name,
        description: item.description ?? '',
        category_id: item.category_id,
        station_id: item.station_id,
        price: item.price,
        cost: item.cost ?? '',
        is_veg: item.is_veg,
        vat_applicable: item.vat_applicable,
        show_on_qr: item.show_on_qr,
        available_from: item.available_from ?? '',
        available_to: item.available_to ?? '',
        sort_order: item.sort_order,
        modifiers: item.modifiers.map((m) => ({ ...m })),
    });
    open.value = true;
}

function save(): void {
    const target = editing.value
        ? MenuItemController.update(editing.value.id)
        : MenuItemController.store();
    form.submit(target, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}

function addModifier(): void {
    form.modifiers.push({ name: '', price: '' });
}

/* ---------- quick row actions ---------- */
function toggleAvailable(item: Item): void {
    router.visit(MenuItemController.toggleAvailability(item.id), {
        preserveScroll: true,
    });
}

function archive(item: Item): void {
    if (
        window.confirm(
            `Archive ${item.name}? It disappears from POS and the QR menu but stays in old bills.`,
        )
    ) {
        router.visit(MenuItemController.archive(item.id), {
            preserveScroll: true,
        });
    }
}

function restore(item: Item): void {
    router.visit(MenuItemController.restore(item.id), { preserveScroll: true });
}

const photo = ref<HTMLInputElement | null>(null);
const photoErrors = ref<string | null>(null);

function uploadPhoto(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file || editing.value === null) return;
    photoErrors.value = null;
    router.post(
        MenuItemController.uploadImage.url(editing.value.id),
        { image: file },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => {
                photoErrors.value = e.image ?? 'Upload failed';
            },
            onSuccess: () => {
                const fresh = props.items.find(
                    (i) => i.id === editing.value?.id,
                );
                if (fresh) editing.value = fresh;
            },
        },
    );
}

function removePhoto(): void {
    if (editing.value === null) return;
    router.visit(MenuItemController.removeImage(editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            const fresh = props.items.find((i) => i.id === editing.value?.id);
            if (fresh) editing.value = fresh;
        },
    });
}

/* ---------- categories & stations (setup tab) ---------- */
const catForm = useForm({
    name: '',
    parent_id: null as number | null,
    sort_order: 0,
    is_active: true,
});
const catEditing = ref<Category | null>(null);

function editCategory(c: Category | null): void {
    catEditing.value = c;
    catForm.clearErrors();
    catForm.name = c?.name ?? '';
    catForm.parent_id = c?.parent_id ?? null;
    catForm.sort_order = c?.sort_order ?? 0;
    catForm.is_active = c?.is_active ?? true;
}

function saveCategory(): void {
    const target = catEditing.value
        ? MenuCategoryController.update(catEditing.value.id)
        : MenuCategoryController.store();
    catForm.submit(target, {
        preserveScroll: true,
        onSuccess: () => editCategory(null),
    });
}

function deleteCategory(c: Category): void {
    if (window.confirm(`Delete the empty category "${c.name}"?`)) {
        router.visit(MenuCategoryController.destroy(c.id), {
            preserveScroll: true,
        });
    }
}

const stationForm = useForm({
    name: '',
    kind: 'kitchen',
    sort_order: 0,
    is_active: true,
});
const stationEditing = ref<Station | null>(null);

function editStation(s: Station | null): void {
    stationEditing.value = s;
    stationForm.clearErrors();
    stationForm.name = s?.name ?? '';
    stationForm.kind = s?.kind ?? 'kitchen';
    stationForm.sort_order = s?.sort_order ?? 0;
    stationForm.is_active = s?.is_active ?? true;
}

function saveStation(): void {
    const target = stationEditing.value
        ? StationController.update(stationEditing.value.id)
        : StationController.store();
    stationForm.submit(target, {
        preserveScroll: true,
        onSuccess: () => editStation(null),
    });
}
</script>

<template>
    <Head title="Menu" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Menu</h1>
                <p class="text-sm text-muted-foreground">
                    {{ counts.active }} items · {{ counts.soldout }} sold out ·
                    prices shown with VAT 0% and service charge 0%
                </p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <div v-if="can.manage" class="flex rounded-lg bg-muted p-1">
                    <button
                        type="button"
                        class="h-9 rounded-md px-4 text-sm font-semibold"
                        :class="
                            tab === 'items'
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="tab = 'items'"
                    >
                        Items
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
                        Categories & stations
                    </button>
                </div>
                <Button
                    v-if="can.manage && tab === 'items'"
                    class="h-11"
                    @click="openCreate"
                >
                    <Plus class="size-4" /> Add item
                </Button>
            </div>
        </div>

        <template v-if="tab === 'items'">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="h-10 rounded-full border px-4 text-sm font-semibold"
                    :class="
                        cat === 'all'
                            ? 'border-foreground bg-foreground text-background'
                            : 'hover:bg-muted'
                    "
                    @click="cat = 'all'"
                >
                    All
                </button>
                <button
                    v-for="c in topCategories"
                    :key="c.id"
                    type="button"
                    class="h-10 rounded-full border px-4 text-sm font-semibold"
                    :class="[
                        cat === c.id
                            ? 'border-foreground bg-foreground text-background'
                            : 'hover:bg-muted',
                        { 'opacity-50': !c.is_active },
                    ]"
                    @click="cat = c.id"
                >
                    {{ c.name }}
                    <span class="ml-1 text-xs opacity-70">{{
                        c.items_count
                    }}</span>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-xs">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Search name or code"
                        aria-label="Search menu"
                        class="h-11 pl-9"
                    />
                </div>
                <div class="flex rounded-lg bg-muted p-1">
                    <button
                        v-for="opt in showOptions"
                        :key="opt.value"
                        type="button"
                        class="h-9 rounded-md px-3 text-sm font-semibold"
                        :class="
                            show === opt.value
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="show = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Item</th>
                            <th class="px-4 py-3 font-medium">Category</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Price
                            </th>
                            <th
                                v-if="can.manage"
                                class="px-4 py-3 text-right font-medium"
                            >
                                Cost
                            </th>
                            <th v-if="can.manage" class="px-4 py-3 font-medium">
                                Margin
                            </th>
                            <th class="px-4 py-3 font-medium">Station</th>
                            <th class="px-4 py-3 font-medium">Selling</th>
                            <th v-if="can.manage" class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in filtered"
                            :key="item.id"
                            class="border-t"
                            :class="{ 'opacity-50': !item.is_active }"
                        >
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        alt=""
                                        class="size-11 rounded-lg object-cover"
                                    />
                                    <div
                                        v-else
                                        class="size-11 rounded-lg bg-muted"
                                    />
                                    <div>
                                        <div class="font-semibold">
                                            {{ item.name }}
                                        </div>
                                        <div
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <span
                                                class="size-2 rounded-full"
                                                :class="
                                                    item.is_veg
                                                        ? 'bg-green-600'
                                                        : 'bg-red-600'
                                                "
                                            />
                                            {{
                                                item.is_veg ? 'Veg' : 'Non-veg'
                                            }}
                                            · {{ item.code }}
                                            <span v-if="item.available_from"
                                                >· {{ item.available_from }}–{{
                                                    item.available_to
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ item.category }}
                            </td>
                            <td
                                class="px-4 py-2 text-right font-semibold tabular-nums"
                            >
                                {{ rs(item.price) }}
                            </td>
                            <td
                                v-if="can.manage"
                                class="px-4 py-2 text-right text-muted-foreground tabular-nums"
                            >
                                {{ rs(item.cost) }}
                            </td>
                            <td v-if="can.manage" class="px-4 py-2">
                                <div
                                    v-if="item.margin !== null"
                                    class="flex items-center gap-2"
                                >
                                    <div
                                        class="h-2 w-16 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full"
                                            :class="
                                                item.margin >= 60
                                                    ? 'bg-green-600'
                                                    : item.margin >= 40
                                                      ? 'bg-orange-500'
                                                      : 'bg-red-600'
                                            "
                                            :style="{
                                                width:
                                                    Math.max(
                                                        0,
                                                        Math.min(
                                                            100,
                                                            item.margin,
                                                        ),
                                                    ) + '%',
                                            }"
                                        />
                                    </div>
                                    <span class="text-xs tabular-nums"
                                        >{{ item.margin }}%</span
                                    >
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >add cost</span
                                >
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ item.station }}
                            </td>
                            <td class="px-4 py-2">
                                <button
                                    v-if="can.availability && item.is_active"
                                    type="button"
                                    role="switch"
                                    :aria-checked="item.is_available"
                                    :aria-label="
                                        (item.is_available
                                            ? 'Mark sold out: '
                                            : 'Back on sale: ') + item.name
                                    "
                                    class="relative h-7 w-12 rounded-full transition"
                                    :class="
                                        item.is_available
                                            ? 'bg-green-600'
                                            : 'bg-muted-foreground/40'
                                    "
                                    @click="toggleAvailable(item)"
                                >
                                    <span
                                        class="absolute top-1 size-5 rounded-full bg-white shadow transition-all"
                                        :class="
                                            item.is_available
                                                ? 'left-6'
                                                : 'left-1'
                                        "
                                    />
                                </button>
                                <Badge
                                    v-else
                                    :variant="
                                        item.is_available
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                >
                                    {{
                                        item.is_available
                                            ? 'On sale'
                                            : 'Sold out'
                                    }}
                                </Badge>
                            </td>
                            <td v-if="can.manage" class="px-4 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        :aria-label="`Edit ${item.name}`"
                                        @click="openEdit(item)"
                                        ><Pencil class="size-4"
                                    /></Button>
                                    <Button
                                        v-if="item.is_active"
                                        size="sm"
                                        variant="ghost"
                                        :aria-label="`Archive ${item.name}`"
                                        @click="archive(item)"
                                        ><Archive class="size-4"
                                    /></Button>
                                    <Button
                                        v-else
                                        size="sm"
                                        variant="ghost"
                                        :aria-label="`Restore ${item.name}`"
                                        @click="restore(item)"
                                        ><RotateCcw class="size-4"
                                    /></Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filtered.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                No items match.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Categories & stations -->
        <div v-else class="grid gap-6 lg:grid-cols-2">
            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <h2 class="text-lg font-bold">Categories</h2>
                <form
                    class="flex flex-wrap items-end gap-2"
                    @submit.prevent="saveCategory"
                >
                    <div class="grid gap-1">
                        <Label for="c-name">{{
                            catEditing ? 'Rename' : 'New category'
                        }}</Label>
                        <Input
                            id="c-name"
                            v-model="catForm.name"
                            class="h-10 w-48"
                            required
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label for="c-parent">Inside</Label>
                        <select
                            id="c-parent"
                            v-model="catForm.parent_id"
                            class="h-10 rounded-md border bg-background px-2 text-sm"
                        >
                            <option :value="null">— top level —</option>
                            <option
                                v-for="c in topCategories.filter(
                                    (t) => t.id !== catEditing?.id,
                                )"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                    </div>
                    <label
                        v-if="catEditing"
                        class="flex h-10 items-center gap-2 text-sm"
                    >
                        <input
                            v-model="catForm.is_active"
                            type="checkbox"
                            class="size-4"
                        />
                        In use
                    </label>
                    <Button
                        type="submit"
                        class="h-10"
                        :disabled="catForm.processing"
                        >{{ catEditing ? 'Save' : 'Add' }}</Button
                    >
                    <Button
                        v-if="catEditing"
                        type="button"
                        variant="ghost"
                        class="h-10"
                        @click="editCategory(null)"
                        >Cancel</Button
                    >
                </form>
                <InputError
                    :message="
                        catForm.errors.name ||
                        catForm.errors.parent_id ||
                        catForm.errors.is_active
                    "
                />
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="c in categories"
                        :key="c.id"
                        class="flex items-center gap-2 px-3 py-2 text-sm"
                        :class="{
                            'pl-8': c.parent_id !== null,
                            'opacity-50': !c.is_active,
                        }"
                    >
                        <span class="font-semibold">{{ c.name }}</span>
                        <span
                            v-if="c.parent_id"
                            class="text-xs text-muted-foreground"
                            >in {{ categoryName(c.parent_id) }}</span
                        >
                        <span class="ml-auto text-xs text-muted-foreground"
                            >{{ c.items_count }} items</span
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            :aria-label="`Edit ${c.name}`"
                            @click="editCategory(c)"
                            ><Pencil class="size-4"
                        /></Button>
                        <Button
                            v-if="c.items_count === 0"
                            size="sm"
                            variant="ghost"
                            :aria-label="`Delete ${c.name}`"
                            @click="deleteCategory(c)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <h2 class="text-lg font-bold">Stations</h2>
                <p class="text-sm text-muted-foreground">
                    Each item sends its order ticket (KOT) to one station.
                </p>
                <form
                    class="flex flex-wrap items-end gap-2"
                    @submit.prevent="saveStation"
                >
                    <div class="grid gap-1">
                        <Label for="s-name">{{
                            stationEditing ? 'Rename' : 'New station'
                        }}</Label>
                        <Input
                            id="s-name"
                            v-model="stationForm.name"
                            class="h-10 w-44"
                            required
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label for="s-kind">Type</Label>
                        <select
                            id="s-kind"
                            v-model="stationForm.kind"
                            class="h-10 rounded-md border bg-background px-2 text-sm"
                        >
                            <option
                                v-for="k in stationKinds"
                                :key="k.value"
                                :value="k.value"
                            >
                                {{ k.label }}
                            </option>
                        </select>
                    </div>
                    <label
                        v-if="stationEditing"
                        class="flex h-10 items-center gap-2 text-sm"
                    >
                        <input
                            v-model="stationForm.is_active"
                            type="checkbox"
                            class="size-4"
                        />
                        In use
                    </label>
                    <Button
                        type="submit"
                        class="h-10"
                        :disabled="stationForm.processing"
                        >{{ stationEditing ? 'Save' : 'Add' }}</Button
                    >
                    <Button
                        v-if="stationEditing"
                        type="button"
                        variant="ghost"
                        class="h-10"
                        @click="editStation(null)"
                        >Cancel</Button
                    >
                </form>
                <InputError
                    :message="
                        stationForm.errors.name ||
                        stationForm.errors.kind ||
                        stationForm.errors.is_active
                    "
                />
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="s in stations"
                        :key="s.id"
                        class="flex items-center gap-2 px-3 py-2 text-sm"
                        :class="{ 'opacity-50': !s.is_active }"
                    >
                        <span class="font-semibold">{{ s.name }}</span>
                        <Badge variant="secondary">{{
                            stationKinds.find((k) => k.value === s.kind)?.label
                        }}</Badge>
                        <span class="ml-auto text-xs text-muted-foreground"
                            >{{ s.items_count }} items</span
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            :aria-label="`Edit ${s.name}`"
                            @click="editStation(s)"
                            ><Pencil class="size-4"
                        /></Button>
                    </li>
                </ul>
            </section>
        </div>
    </div>

    <!-- Item drawer -->
    <Sheet v-model:open="open">
        <SheetContent class="w-full overflow-y-auto sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>{{
                    editing ? `Edit ${editing.name}` : 'Add menu item'
                }}</SheetTitle>
                <SheetDescription
                    >Price changes are recorded in the audit
                    log.</SheetDescription
                >
            </SheetHeader>

            <form class="flex flex-col gap-4 px-4 pb-6" @submit.prevent="save">
                <div v-if="editing" class="flex items-center gap-3">
                    <img
                        v-if="editing.image_url"
                        :src="editing.image_url"
                        alt=""
                        class="size-20 rounded-xl object-cover"
                    />
                    <div
                        v-else
                        class="flex size-20 items-center justify-center rounded-xl bg-muted"
                    >
                        <ImagePlus class="size-6 text-muted-foreground" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <input
                            ref="photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="uploadPhoto"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="photo?.click()"
                            >{{
                                editing.image_url ? 'Change photo' : 'Add photo'
                            }}</Button
                        >
                        <Button
                            v-if="editing.image_url"
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="removePhoto"
                            >Remove photo</Button
                        >
                        <span class="text-xs text-muted-foreground"
                            >JPG, PNG or WebP, up to 2 MB</span
                        >
                        <InputError :message="photoErrors ?? undefined" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2 grid gap-2">
                        <Label for="m-name">Name</Label>
                        <Input id="m-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="m-code">Code</Label>
                        <Input
                            id="m-code"
                            v-model="form.code"
                            required
                            placeholder="MOMO-01"
                            class="uppercase"
                        />
                        <InputError :message="form.errors.code" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="m-cat">Category</Label>
                        <select
                            id="m-cat"
                            v-model="form.category_id"
                            class="h-10 rounded-md border bg-background px-2 text-sm"
                            required
                        >
                            <template v-for="c in topCategories" :key="c.id">
                                <option :value="c.id" :disabled="!c.is_active">
                                    {{ c.name }}
                                </option>
                                <option
                                    v-for="s in categories.filter(
                                        (x) => x.parent_id === c.id,
                                    )"
                                    :key="s.id"
                                    :value="s.id"
                                    :disabled="!s.is_active"
                                >
                                    — {{ s.name }}
                                </option>
                            </template>
                        </select>
                        <InputError :message="form.errors.category_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="m-station">Prepared at</Label>
                        <select
                            id="m-station"
                            v-model="form.station_id"
                            class="h-10 rounded-md border bg-background px-2 text-sm"
                            required
                        >
                            <option
                                v-for="s in stations"
                                :key="s.id"
                                :value="s.id"
                                :disabled="!s.is_active"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.station_id" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="m-price">Selling price (Rs)</Label>
                        <Input
                            id="m-price"
                            v-model="form.price"
                            inputmode="decimal"
                            required
                        />
                        <InputError :message="form.errors.price" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="m-cost">Cost per plate (Rs)</Label>
                        <Input
                            id="m-cost"
                            v-model="form.cost"
                            inputmode="decimal"
                            placeholder="optional"
                        />
                        <InputError :message="form.errors.cost" />
                    </div>
                </div>

                <div
                    v-if="liveMargin"
                    class="flex items-center justify-between rounded-xl p-3 text-sm font-semibold"
                    :class="
                        liveMargin.percent >= 60
                            ? 'bg-green-50 text-green-800'
                            : liveMargin.percent >= 40
                              ? 'bg-orange-50 text-orange-800'
                              : 'bg-red-50 text-red-800'
                    "
                >
                    <span
                        >Profit per plate Rs
                        {{ liveMargin.profit.toLocaleString('en-IN') }}</span
                    >
                    <span>Margin {{ liveMargin.percent }}%</span>
                </div>

                <div class="grid gap-2">
                    <Label for="m-desc"
                        >Description (shown on the QR menu)</Label
                    >
                    <textarea
                        id="m-desc"
                        v-model="form.description"
                        rows="2"
                        maxlength="500"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="h-10 rounded-lg border px-3 text-sm font-semibold"
                        :class="
                            !form.is_veg
                                ? 'border-red-600 bg-red-50 text-red-700'
                                : ''
                        "
                        @click="form.is_veg = false"
                    >
                        Non-veg
                    </button>
                    <button
                        type="button"
                        class="h-10 rounded-lg border px-3 text-sm font-semibold"
                        :class="
                            form.is_veg
                                ? 'border-green-600 bg-green-50 text-green-700'
                                : ''
                        "
                        @click="form.is_veg = true"
                    >
                        Veg
                    </button>
                    <label
                        class="flex h-10 items-center gap-2 rounded-lg border px-3 text-sm"
                        ><input
                            v-model="form.show_on_qr"
                            type="checkbox"
                            class="size-4"
                        />
                        Show on QR menu</label
                    >
                    <label
                        class="flex h-10 items-center gap-2 rounded-lg border px-3 text-sm"
                        ><input
                            v-model="form.vat_applicable"
                            type="checkbox"
                            class="size-4"
                        />
                        VAT applies</label
                    >
                </div>

                <div class="grid gap-2">
                    <Label>Sold only between (optional)</Label>
                    <div class="flex items-center gap-2">
                        <Input
                            v-model="form.available_from"
                            type="time"
                            class="h-10 w-32"
                            aria-label="From"
                        />
                        <span class="text-sm text-muted-foreground">to</span>
                        <Input
                            v-model="form.available_to"
                            type="time"
                            class="h-10 w-32"
                            aria-label="To"
                        />
                        <Button
                            v-if="form.available_from || form.available_to"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="
                                form.available_from = '';
                                form.available_to = '';
                            "
                            >Clear</Button
                        >
                    </div>
                    <InputError
                        :message="
                            form.errors.available_from ||
                            form.errors.available_to
                        "
                    />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center">
                        <Label>Extras (e.g. extra sauce +Rs 20)</Label>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            class="ml-auto"
                            @click="addModifier"
                            ><Plus class="size-4" /> Add extra</Button
                        >
                    </div>
                    <div
                        v-for="(m, i) in form.modifiers"
                        :key="i"
                        class="flex items-start gap-2"
                    >
                        <div class="flex-1">
                            <Input
                                v-model="m.name"
                                placeholder="Name"
                                :aria-label="`Extra ${i + 1} name`"
                            />
                            <InputError
                                :message="
                                    (form.errors as Record<string, string>)[
                                        `modifiers.${i}.name`
                                    ]
                                "
                            />
                        </div>
                        <div class="w-28">
                            <Input
                                v-model="m.price"
                                placeholder="Rs"
                                inputmode="decimal"
                                :aria-label="`Extra ${i + 1} price`"
                            />
                            <InputError
                                :message="
                                    (form.errors as Record<string, string>)[
                                        `modifiers.${i}.price`
                                    ]
                                "
                            />
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            :aria-label="`Remove extra ${i + 1}`"
                            @click="form.modifiers.splice(i, 1)"
                            ><X class="size-4"
                        /></Button>
                    </div>
                </div>

                <Button
                    type="submit"
                    class="h-11"
                    :disabled="form.processing"
                    >{{ editing ? 'Save changes' : 'Add to menu' }}</Button
                >
            </form>
        </SheetContent>
    </Sheet>
</template>
