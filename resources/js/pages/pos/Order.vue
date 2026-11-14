<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    ChefHat,
    Combine,
    Minus,
    Plus,
    Printer,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PosController from '@/actions/App/Http/Controllers/Pos/PosController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rs, toPaisa } from '@/lib/money';
import { index as posIndex } from '@/routes/pos';

type Modifier = { id: number; name: string; price: string };
type MenuItem = {
    id: number;
    code: string;
    name: string;
    category_id: number;
    price: string;
    is_veg: boolean;
    orderable: boolean;
    image_url: string | null;
    modifiers: Modifier[];
};
type Line = {
    id: number;
    menu_item_id: number;
    name: string;
    unit_price: string;
    modifiers: Modifier[];
    quantity: number;
    line_total: string;
    note: string | null;
    status: 'draft' | 'sent' | 'preparing' | 'ready' | 'served' | 'void';
    status_label: string;
    void_reason: string | null;
};
type Order = {
    id: number;
    number: string;
    label: string;
    type: 'dine_in' | 'takeaway' | 'delivery';
    type_label: string;
    table_id: number | null;
    guests: number;
    waiter: string;
    customer_name: string | null;
    customer_phone: string | null;
    note: string | null;
    subtotal: string;
    items: Line[];
    kots: { id: number; number: number; station: string; printed: boolean }[];
};
type Brief = { id: number; label: string; code?: string };

const props = defineProps<{
    order: Order;
    menu: {
        categories: { id: number; name: string; parent_id: number | null }[];
        items: MenuItem[];
    };
    freeTables: Brief[];
    otherOrders: Brief[];
    can: { pay: boolean; approve: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'POS', href: posIndex() }] },
});

/* errors about the whole order come back under the key "order" */
function orderError(f: { errors: object }): string | undefined {
    return (f.errors as Record<string, string | undefined>).order;
}

/* menu browsing */
const category = ref<number | 'all'>('all');
const search = ref('');
const visibleItems = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.menu.items.filter((i) => {
        if (q !== '')
            return (
                i.name.toLowerCase().includes(q) ||
                i.code.toLowerCase().includes(q)
            );
        if (category.value === 'all') return true;
        const cat = props.menu.categories.find((c) => c.id === i.category_id);
        return (
            i.category_id === category.value ||
            cat?.parent_id === category.value
        );
    });
});
const topCategories = computed(() =>
    props.menu.categories.filter((c) => c.parent_id === null),
);

/* adding */
const picking = ref<MenuItem | null>(null);
const addForm = useForm({
    menu_item_id: 0,
    quantity: 1,
    modifier_ids: [] as number[],
    note: '',
});

function tapItem(item: MenuItem): void {
    if (!item.orderable) return;
    addForm.reset();
    addForm.clearErrors();
    addForm.menu_item_id = item.id;
    if (item.modifiers.length > 0) {
        picking.value = item;
        return;
    }
    submitAdd();
}
function toggleModifier(id: number): void {
    const i = addForm.modifier_ids.indexOf(id);
    if (i === -1) addForm.modifier_ids.push(id);
    else addForm.modifier_ids.splice(i, 1);
}
const pickTotal = computed(() => {
    if (picking.value === null) return 0;
    let p = toPaisa(picking.value.price);
    picking.value.modifiers.forEach(
        (m) => addForm.modifier_ids.includes(m.id) && (p += toPaisa(m.price)),
    );
    return p * addForm.quantity;
});
function submitAdd(): void {
    addForm.submit(PosController.addItem(props.order.id), {
        preserveScroll: true,
        onSuccess: () => (picking.value = null),
    });
}

/* lines */
const drafts = computed(() =>
    props.order.items.filter((l) => l.status === 'draft'),
);
const sent = computed(() =>
    props.order.items.filter((l) => l.status !== 'draft'),
);

function changeQty(line: Line, delta: number): void {
    const q = line.quantity + delta;
    if (q < 1) {
        router.visit(PosController.removeItem(line.id), {
            preserveScroll: true,
        });
        return;
    }
    router.visit(PosController.updateItem(line.id), {
        data: { quantity: q, note: line.note },
        preserveScroll: true,
    });
}
function served(line: Line): void {
    router.visit(PosController.markServed(line.id), { preserveScroll: true });
}

const sending = ref(false);
function sendKot(): void {
    router.visit(PosController.sendKot(props.order.id), {
        preserveScroll: true,
        onStart: () => (sending.value = true),
        onFinish: () => (sending.value = false),
    });
}
function printKot(id: number): void {
    window.open(
        PosController.printKot(id).url,
        '_blank',
        'width=380,height=600',
    );
}

/* KOTs just sent → open print windows */
let off: (() => void) | undefined;
onMounted(() => {
    off = router.on('flash', (event) => {
        const ids = (event as CustomEvent).detail?.flash?.printKots as
            | number[]
            | undefined;
        ids?.forEach((id) => printKot(id));
    });
});
onBeforeUnmount(() => off?.());

/* void */
const voiding = ref<Line | null>(null);
const voidForm = useForm({
    reason: '',
    approver_username: '',
    approver_secret: '',
});
function startVoid(line: Line): void {
    voidForm.reset();
    voidForm.clearErrors();
    voiding.value = line;
}
function submitVoid(): void {
    if (voiding.value === null) return;
    voidForm.submit(PosController.voidItem(voiding.value.id), {
        preserveScroll: true,
        onSuccess: () => (voiding.value = null),
        onFinish: () => (voidForm.approver_secret = ''),
    });
}

/* more actions */
const action = ref<'move' | 'merge' | 'cancel' | null>(null);
const moveForm = useForm({ dining_table_id: null as number | null });
const mergeForm = useForm({ source_order_id: null as number | null });
const cancelForm = useForm({ reason: '' });
function submitMove(): void {
    moveForm.submit(PosController.move(props.order.id), {
        preserveScroll: true,
        onSuccess: () => (action.value = null),
    });
}
function submitMerge(): void {
    mergeForm.submit(PosController.merge(props.order.id), {
        preserveScroll: true,
        onSuccess: () => (action.value = null),
    });
}
function submitCancel(): void {
    cancelForm.submit(PosController.cancel(props.order.id));
}

const STATUS_STYLE: Record<Line['status'], string> = {
    draft: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    sent: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    preparing:
        'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
    ready: 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
    served: 'bg-muted text-muted-foreground',
    void: 'bg-red-100 text-red-900 line-through dark:bg-red-900/40 dark:text-red-100',
};
</script>

<template>
    <Head :title="order.label" />

    <div
        class="grid min-h-[calc(100vh-4rem)] gap-4 p-4 lg:grid-cols-[1fr_400px]"
    >
        <!-- menu -->
        <section class="flex min-w-0 flex-col gap-3">
            <div class="flex items-center gap-2">
                <Link
                    :href="posIndex().url"
                    class="flex size-11 items-center justify-center rounded-lg border"
                    aria-label="Back to tables"
                    ><ArrowLeft class="size-5"
                /></Link>
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="h-11 pl-9"
                        placeholder="Search item or code"
                    />
                </div>
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="c in [
                        { id: 'all' as const, name: 'All' },
                        ...topCategories,
                    ]"
                    :key="c.id"
                    type="button"
                    class="h-10 shrink-0 rounded-full border px-4 text-sm font-semibold"
                    :class="
                        category === c.id
                            ? 'border-foreground bg-foreground text-background'
                            : 'hover:bg-muted'
                    "
                    @click="
                        category = c.id;
                        search = '';
                    "
                >
                    {{ c.name }}
                </button>
            </div>
            <InputError
                :message="addForm.errors.menu_item_id || orderError(addForm)"
            />
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                <button
                    v-for="item in visibleItems"
                    :key="item.id"
                    type="button"
                    class="flex min-h-20 flex-col items-start rounded-xl border p-3 text-left transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="!item.orderable || addForm.processing"
                    @click="tapItem(item)"
                >
                    <span
                        class="flex w-full items-start gap-1.5 leading-tight font-semibold"
                    >
                        <span
                            class="mt-1 size-2.5 shrink-0 rounded-sm border"
                            :class="
                                item.is_veg
                                    ? 'border-green-600 bg-green-500'
                                    : 'border-red-700 bg-red-600'
                            "
                        />
                        {{ item.name }}
                    </span>
                    <span
                        class="mt-auto flex w-full justify-between pt-1 text-sm text-muted-foreground tabular-nums"
                    >
                        <span>{{ rs(item.price) }}</span>
                        <span
                            v-if="!item.orderable"
                            class="text-xs font-bold text-red-600"
                            >Sold out</span
                        >
                        <span v-else-if="item.modifiers.length" class="text-xs"
                            >+ extras</span
                        >
                    </span>
                </button>
            </div>
        </section>

        <!-- order -->
        <aside
            class="flex flex-col gap-3 rounded-xl border p-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-6rem)]"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h1 class="text-xl font-bold">{{ order.label }}</h1>
                    <p class="text-xs text-muted-foreground">
                        Order #{{ order.number }} · {{ order.type_label }} ·
                        {{ order.guests }} guests · {{ order.waiter }}
                    </p>
                    <p
                        v-if="order.customer_name"
                        class="text-xs text-muted-foreground"
                    >
                        {{ order.customer_name }}
                        {{ order.customer_phone ?? '' }}
                    </p>
                </div>
            </div>

            <div class="-mx-1 flex flex-1 flex-col gap-1 overflow-y-auto px-1">
                <p
                    v-if="order.items.length === 0"
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Tap items on the left to add them.
                </p>

                <div
                    v-for="line in [...sent, ...drafts]"
                    :key="line.id"
                    class="flex items-center gap-2 rounded-lg border p-2"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="truncate font-semibold"
                                :class="
                                    line.status === 'void'
                                        ? 'line-through opacity-60'
                                        : ''
                                "
                                >{{ line.name }}</span
                            >
                            <span
                                class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                :class="STATUS_STYLE[line.status]"
                                >{{ line.status_label }}</span
                            >
                        </div>
                        <div
                            v-if="line.modifiers.length"
                            class="truncate text-xs text-muted-foreground"
                        >
                            + {{ line.modifiers.map((m) => m.name).join(', ') }}
                        </div>
                        <div
                            v-if="line.note"
                            class="truncate text-xs text-muted-foreground"
                        >
                            » {{ line.note }}
                        </div>
                        <div
                            v-if="line.void_reason"
                            class="truncate text-xs text-red-600"
                        >
                            Void: {{ line.void_reason }}
                        </div>
                        <div class="text-xs text-muted-foreground tabular-nums">
                            {{ line.quantity }} × {{ rs(line.unit_price) }}
                        </div>
                    </div>
                    <template v-if="line.status === 'draft'">
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-md border"
                            aria-label="Less"
                            @click="changeQty(line, -1)"
                        >
                            <Trash2
                                v-if="line.quantity === 1"
                                class="size-4"
                            /><Minus v-else class="size-4" />
                        </button>
                        <span class="w-6 text-center font-bold tabular-nums">{{
                            line.quantity
                        }}</span>
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-md border"
                            aria-label="More"
                            @click="changeQty(line, 1)"
                        >
                            <Plus class="size-4" />
                        </button>
                    </template>
                    <template v-else-if="line.status !== 'void'">
                        <button
                            v-if="line.status !== 'served'"
                            type="button"
                            class="h-9 rounded-md border px-2 text-xs font-semibold"
                            @click="served(line)"
                        >
                            Served
                        </button>
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-md border text-red-600"
                            aria-label="Void item"
                            @click="startVoid(line)"
                        >
                            <X class="size-4" />
                        </button>
                    </template>
                    <span
                        class="w-20 text-right text-sm font-semibold tabular-nums"
                        :class="
                            line.status === 'void'
                                ? 'line-through opacity-50'
                                : ''
                        "
                        >{{ rs(line.line_total) }}</span
                    >
                </div>
            </div>

            <div
                v-if="order.kots.length"
                class="flex flex-wrap gap-1.5 border-t pt-2"
            >
                <button
                    v-for="k in order.kots"
                    :key="k.id"
                    type="button"
                    class="flex h-8 items-center gap-1 rounded-md border px-2 text-xs"
                    @click="printKot(k.id)"
                >
                    <Printer class="size-3" /> KOT {{ k.number }} ·
                    {{ k.station }}
                </button>
            </div>

            <div
                class="flex items-center justify-between border-t pt-3 text-lg font-bold"
            >
                <span>Subtotal</span
                ><span class="tabular-nums">{{ rs(order.subtotal) }}</span>
            </div>
            <p class="-mt-2 text-xs text-muted-foreground">
                Discount, service charge and VAT are added at billing.
            </p>

            <Button
                class="h-14 text-base"
                :disabled="drafts.length === 0 || sending"
                @click="sendKot"
            >
                <ChefHat class="size-5" /> Send KOT{{
                    drafts.length ? ` (${drafts.length})` : ''
                }}
            </Button>
            <div class="grid grid-cols-3 gap-2">
                <Button
                    variant="outline"
                    class="h-11"
                    :disabled="order.type !== 'dine_in'"
                    @click="action = 'move'"
                    ><ArrowRightLeft class="size-4" /> Move</Button
                >
                <Button
                    variant="outline"
                    class="h-11"
                    :disabled="otherOrders.length === 0"
                    @click="action = 'merge'"
                    ><Combine class="size-4" /> Merge</Button
                >
                <Button
                    variant="outline"
                    class="h-11 text-red-600"
                    @click="action = 'cancel'"
                    ><X class="size-4" /> Cancel</Button
                >
            </div>
        </aside>
    </div>

    <!-- extras picker -->
    <Dialog
        :open="picking !== null"
        @update:open="(v) => !v && (picking = null)"
    >
        <DialogContent v-if="picking">
            <DialogHeader>
                <DialogTitle>{{ picking.name }}</DialogTitle>
                <DialogDescription
                    >{{ rs(picking.price) }} · choose extras</DialogDescription
                >
            </DialogHeader>
            <div class="flex flex-col gap-2">
                <label
                    v-for="m in picking.modifiers"
                    :key="m.id"
                    class="flex h-12 items-center gap-3 rounded-lg border px-3"
                >
                    <input
                        type="checkbox"
                        class="size-5"
                        :checked="addForm.modifier_ids.includes(m.id)"
                        @change="toggleModifier(m.id)"
                    />
                    <span class="flex-1">{{ m.name }}</span>
                    <span class="text-sm text-muted-foreground tabular-nums"
                        >+ {{ rs(m.price) }}</span
                    >
                </label>
                <InputError :message="addForm.errors.modifier_ids" />
                <div class="flex items-center gap-3 pt-2">
                    <Label>Qty</Label>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-md border"
                        @click="
                            addForm.quantity = Math.max(1, addForm.quantity - 1)
                        "
                    >
                        <Minus class="size-4" />
                    </button>
                    <span class="w-8 text-center text-lg font-bold">{{
                        addForm.quantity
                    }}</span>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-md border"
                        @click="
                            addForm.quantity = Math.min(
                                99,
                                addForm.quantity + 1,
                            )
                        "
                    >
                        <Plus class="size-4" />
                    </button>
                </div>
                <Input
                    v-model="addForm.note"
                    placeholder="Note for kitchen (optional)"
                />
            </div>
            <DialogFooter>
                <Button
                    class="h-12 w-full text-base"
                    :disabled="addForm.processing"
                    @click="submitAdd"
                    >Add · {{ rs(pickTotal) }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- void -->
    <Dialog
        :open="voiding !== null"
        @update:open="(v) => !v && (voiding = null)"
    >
        <DialogContent v-if="voiding">
            <DialogHeader>
                <DialogTitle
                    >Void {{ voiding.quantity }} ×
                    {{ voiding.name }}</DialogTitle
                >
                <DialogDescription
                    >This item was sent to the kitchen. The reason and approver
                    are saved in the audit log.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3" @submit.prevent="submitVoid">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="r in [
                            'Customer changed mind',
                            'Wrong item',
                            'Kitchen mistake',
                            'Too late',
                        ]"
                        :key="r"
                        type="button"
                        class="h-9 rounded-full border px-3 text-sm"
                        :class="
                            voidForm.reason === r
                                ? 'border-foreground bg-foreground text-background'
                                : ''
                        "
                        @click="voidForm.reason = r"
                    >
                        {{ r }}
                    </button>
                </div>
                <Input v-model="voidForm.reason" placeholder="Reason" />
                <InputError :message="voidForm.errors.reason" />
                <template v-if="!can.approve">
                    <p class="text-sm font-semibold">Manager approval</p>
                    <Input
                        v-model="voidForm.approver_username"
                        placeholder="Manager username"
                        autocomplete="off"
                    />
                    <Input
                        v-model="voidForm.approver_secret"
                        type="password"
                        placeholder="Manager PIN or password"
                        autocomplete="off"
                    />
                    <InputError :message="voidForm.errors.approver_username" />
                </template>
                <Button
                    type="submit"
                    variant="destructive"
                    class="h-12"
                    :disabled="voidForm.processing"
                    >Void item</Button
                >
            </form>
        </DialogContent>
    </Dialog>

    <!-- move / merge / cancel -->
    <Dialog :open="action !== null" @update:open="(v) => !v && (action = null)">
        <DialogContent>
            <template v-if="action === 'move'">
                <DialogHeader
                    ><DialogTitle>Move {{ order.label }} to…</DialogTitle
                    ><DialogDescription
                        >Pick a free table.</DialogDescription
                    ></DialogHeader
                >
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="t in freeTables"
                        :key="t.id"
                        type="button"
                        class="h-12 rounded-lg border font-bold"
                        :class="
                            moveForm.dining_table_id === t.id
                                ? 'border-foreground bg-foreground text-background'
                                : ''
                        "
                        @click="moveForm.dining_table_id = t.id"
                    >
                        {{ t.code }}
                    </button>
                </div>
                <InputError
                    :message="
                        moveForm.errors.dining_table_id || orderError(moveForm)
                    "
                />
                <Button
                    class="h-12"
                    :disabled="
                        moveForm.dining_table_id === null || moveForm.processing
                    "
                    @click="submitMove"
                    >Move order</Button
                >
            </template>
            <template v-else-if="action === 'merge'">
                <DialogHeader
                    ><DialogTitle>Merge into {{ order.label }}</DialogTitle
                    ><DialogDescription
                        >Items of the chosen order move here, and its table is
                        freed.</DialogDescription
                    ></DialogHeader
                >
                <div class="flex flex-col gap-2">
                    <button
                        v-for="o in otherOrders"
                        :key="o.id"
                        type="button"
                        class="h-12 rounded-lg border px-3 text-left font-semibold"
                        :class="
                            mergeForm.source_order_id === o.id
                                ? 'border-foreground bg-foreground text-background'
                                : ''
                        "
                        @click="mergeForm.source_order_id = o.id"
                    >
                        {{ o.label }}
                    </button>
                </div>
                <InputError
                    :message="
                        mergeForm.errors.source_order_id ||
                        orderError(mergeForm)
                    "
                />
                <Button
                    class="h-12"
                    :disabled="
                        mergeForm.source_order_id === null ||
                        mergeForm.processing
                    "
                    @click="submitMerge"
                    >Merge orders</Button
                >
            </template>
            <template v-else-if="action === 'cancel'">
                <DialogHeader
                    ><DialogTitle>Cancel order #{{ order.number }}?</DialogTitle
                    ><DialogDescription
                        >Only possible when nothing is live in the kitchen. Void
                        sent items first.</DialogDescription
                    ></DialogHeader
                >
                <Input v-model="cancelForm.reason" placeholder="Reason" />
                <InputError
                    :message="
                        cancelForm.errors.reason || orderError(cancelForm)
                    "
                />
                <Button
                    variant="destructive"
                    class="h-12"
                    :disabled="cancelForm.processing"
                    @click="submitCancel"
                    >Cancel order</Button
                >
            </template>
        </DialogContent>
    </Dialog>
</template>
