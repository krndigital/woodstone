<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BellRing,
    GlassWater,
    HandHelping,
    Phone,
    Receipt,
    Search,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import GuestController from '@/actions/App/Http/Controllers/Guest/GuestController';
import { rs } from '@/lib/money';

type Item = {
    id: number;
    name: string;
    description: string | null;
    category_id: number;
    price: string;
    is_veg: boolean;
    available: boolean;
    image_url: string | null;
};

const props = defineProps<{
    token: string;
    table: { label: string; can_call: boolean };
    restaurant: { name: string; phone: string | null };
    categories: { id: number; name: string; parent_id: number | null }[];
    items: Item[];
    callTypes: { value: 'waiter' | 'bill' | 'water' | 'help'; label: string }[];
}>();

const ICONS = {
    waiter: BellRing,
    bill: Receipt,
    water: GlassWater,
    help: HandHelping,
};

const tab = ref<'call' | 'menu'>('call');
const q = ref('');
const cat = ref<number | 'all'>('all');
const top = computed(() =>
    props.categories.filter((c) => c.parent_id === null),
);
const shown = computed(() =>
    props.items.filter((i) => {
        if (q.value.trim())
            return i.name.toLowerCase().includes(q.value.trim().toLowerCase());
        if (cat.value === 'all') return true;
        const c = props.categories.find((x) => x.id === i.category_id);
        return i.category_id === cat.value || c?.parent_id === cat.value;
    }),
);

const form = useForm({
    type: 'waiter' as 'waiter' | 'bill' | 'water' | 'help',
    note: '',
});
const callId = ref<string | null>(null);
const status = ref<{
    status: string;
    type: string;
    waiter: string | null;
} | null>(null);
let poll: ReturnType<typeof setInterval> | undefined;

async function check(): Promise<void> {
    if (!callId.value) return;
    try {
        const res = await fetch(
            GuestController.status({
                token: props.token,
                publicId: callId.value,
            }).url,
            { headers: { Accept: 'application/json' } },
        );
        if (res.ok) status.value = await res.json();
        if (status.value?.status === 'done') clearInterval(poll);
    } catch {
        /* offline: try again next tick */
    }
}
function send(type: 'waiter' | 'bill' | 'water' | 'help'): void {
    form.type = type;
    form.submit(GuestController.call(props.token), {
        preserveScroll: true,
        onSuccess: () => form.reset('note'),
    });
}
let off: (() => void) | undefined;
onMounted(() => {
    off = router.on('flash', (event) => {
        const id = (event as CustomEvent).detail?.flash?.call as
            | string
            | undefined;
        if (!id) return;
        callId.value = id;
        status.value = { status: 'sent', type: '', waiter: null };
        clearInterval(poll);
        poll = setInterval(check, 4000);
    });
});
onBeforeUnmount(() => {
    off?.();
    clearInterval(poll);
});
</script>

<template>
    <Head :title="`${table.label} · ${restaurant.name}`" />
    <div
        class="mx-auto flex min-h-screen max-w-md flex-col bg-background text-foreground"
    >
        <header class="bg-orange-600 px-5 pt-6 pb-5 text-white">
            <div class="text-sm opacity-90">{{ restaurant.name }}</div>
            <div class="text-3xl font-bold">{{ table.label }}</div>
        </header>
        <nav class="sticky top-0 z-10 flex border-b bg-background">
            <button
                v-for="t in [
                    ['call', 'Call staff'],
                    ['menu', 'Menu'],
                ] as const"
                :key="t[0]"
                type="button"
                class="h-12 flex-1 text-sm font-semibold"
                :class="
                    tab === t[0]
                        ? 'border-b-2 border-orange-600 text-orange-700'
                        : 'text-muted-foreground'
                "
                @click="tab = t[0]"
            >
                {{ t[1] }}
            </button>
        </nav>

        <main v-if="tab === 'call'" class="flex flex-col gap-4 p-5">
            <div
                v-if="status"
                class="rounded-xl border-2 p-4 text-center"
                :class="
                    status.status === 'done'
                        ? 'border-green-400 bg-green-50 dark:bg-green-950/40'
                        : status.status === 'accepted'
                          ? 'border-sky-400 bg-sky-50 dark:bg-sky-950/40'
                          : 'border-orange-400 bg-orange-50 dark:bg-orange-950/40'
                "
            >
                <div class="text-lg font-bold">
                    {{
                        status.status === 'done'
                            ? 'Done. Thank you!'
                            : status.status === 'accepted'
                              ? `${status.waiter ?? 'A waiter'} is coming`
                              : 'Sent — please wait'
                    }}
                </div>
                <div class="text-sm text-muted-foreground">
                    {{ status.type }}
                </div>
            </div>

            <p v-if="!table.can_call" class="rounded-xl bg-muted p-4 text-sm">
                Calling is available once staff open your table. You can still
                look at the menu.
            </p>
            <div class="grid grid-cols-2 gap-3">
                <button
                    v-for="c in callTypes"
                    :key="c.value"
                    type="button"
                    class="flex h-28 flex-col items-center justify-center gap-2 rounded-2xl border-2 text-base font-semibold active:scale-95 disabled:opacity-40"
                    :disabled="!table.can_call || form.processing"
                    @click="send(c.value)"
                >
                    <component
                        :is="ICONS[c.value]"
                        class="size-8 text-orange-600"
                    />
                    {{ c.label }}
                </button>
            </div>
            <input
                v-model="form.note"
                maxlength="120"
                placeholder="Note (optional), e.g. 2 more plates"
                class="h-11 rounded-lg border bg-background px-3"
                :disabled="!table.can_call"
            />
            <p
                v-if="form.errors.type || form.errors.note"
                class="text-sm font-semibold text-red-600"
            >
                {{ form.errors.type || form.errors.note }}
            </p>
            <a
                v-if="restaurant.phone"
                :href="`tel:${restaurant.phone}`"
                class="flex h-12 items-center justify-center gap-2 rounded-xl border text-sm font-semibold"
                ><Phone class="size-4" /> Call reception</a
            >
        </main>

        <main v-else class="flex flex-col gap-3 p-4">
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                /><input
                    v-model="q"
                    placeholder="Search"
                    class="h-11 w-full rounded-lg border bg-background pr-3 pl-9"
                />
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="c in [{ id: 'all' as const, name: 'All' }, ...top]"
                    :key="c.id"
                    type="button"
                    class="h-9 shrink-0 rounded-full border px-3 text-sm font-semibold"
                    :class="
                        cat === c.id
                            ? 'border-orange-600 bg-orange-600 text-white'
                            : ''
                    "
                    @click="cat = c.id"
                >
                    {{ c.name }}
                </button>
            </div>
            <article
                v-for="i in shown"
                :key="i.id"
                class="flex gap-3 rounded-xl border p-3"
                :class="i.available ? '' : 'opacity-50'"
            >
                <img
                    v-if="i.image_url"
                    :src="i.image_url"
                    alt=""
                    class="size-20 shrink-0 rounded-lg object-cover"
                    loading="lazy"
                />
                <div class="flex min-w-0 flex-1 flex-col">
                    <div class="flex items-start gap-1.5 font-semibold">
                        <span
                            class="mt-1 size-2.5 shrink-0 rounded-sm border"
                            :class="
                                i.is_veg
                                    ? 'border-green-600 bg-green-500'
                                    : 'border-red-700 bg-red-600'
                            "
                        />{{ i.name }}
                    </div>
                    <p
                        v-if="i.description"
                        class="line-clamp-2 text-xs text-muted-foreground"
                    >
                        {{ i.description }}
                    </p>
                    <div class="mt-auto flex justify-between pt-1 text-sm">
                        <span class="font-bold">{{ rs(i.price) }}</span
                        ><span
                            v-if="!i.available"
                            class="text-xs font-bold text-red-600"
                            >Not available now</span
                        >
                    </div>
                </div>
            </article>
            <p
                v-if="shown.length === 0"
                class="py-10 text-center text-muted-foreground"
            >
                Nothing here.
            </p>
        </main>
        <footer class="mt-auto p-4 text-center text-xs text-muted-foreground">
            Prices include all charges shown on the bill.
        </footer>
    </div>
</template>
