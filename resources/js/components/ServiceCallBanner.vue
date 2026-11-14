<script setup lang="ts">
import { BellRing, Check, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ServiceCallController from '@/actions/App/Http/Controllers/Guest/ServiceCallController';
import { usePermissions } from '@/composables/usePermissions';

type Call = {
    id: number;
    table: string;
    type: string;
    type_label: string;
    note: string | null;
    status: 'sent' | 'accepted';
    accepted_by: string | null;
    created_at: string | null;
};

const { can } = usePermissions();
const calls = ref<Call[]>([]);
const now = ref(Date.now());
const hidden = ref(false);
const seen = new Set<number>();
let timer: ReturnType<typeof setInterval> | undefined;
let audio: AudioContext | null = null;

const TONES: Record<string, number[]> = {
    waiter: [880, 660],
    bill: [523, 659, 784],
    water: [700, 700],
    help: [600, 900],
    ping: [988, 1319],
};

function ring(type: string): void {
    try {
        audio ??= new AudioContext();
        const ctx = audio;
        (TONES[type] ?? [880]).forEach((f, i) => {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.frequency.value = f;
            g.gain.setValueAtTime(0.15, ctx.currentTime + i * 0.25);
            g.gain.exponentialRampToValueAtTime(
                0.001,
                ctx.currentTime + i * 0.25 + 0.22,
            );
            o.connect(g).connect(ctx.destination);
            o.start(ctx.currentTime + i * 0.25);
            o.stop(ctx.currentTime + i * 0.25 + 0.24);
        });
        navigator.vibrate?.([200, 100, 200]);
    } catch {
        /* sound blocked until the user touches the page */
    }
}

function xsrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

async function load(): Promise<void> {
    if (document.hidden) return;
    try {
        const res = await fetch(ServiceCallController.pending().url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!res.ok) return;
        const data = (await res.json()) as { calls: Call[] };
        calls.value = data.calls;
        now.value = Date.now();
        data.calls.forEach((c) => {
            const age = c.created_at
                ? (Date.now() - new Date(c.created_at).getTime()) / 1000
                : 0;
            // Ring once when new, and again every minute while nobody accepts (FR-16.8).
            if (
                c.status === 'sent' &&
                (!seen.has(c.id) || Math.floor(age) % 60 < 10)
            ) {
                ring(c.type);
                hidden.value = false;
            }
            seen.add(c.id);
        });
    } catch {
        /* offline */
    }
}

async function act(c: Call, action: 'accept' | 'done'): Promise<void> {
    const route =
        action === 'accept'
            ? ServiceCallController.accept(c.id)
            : ServiceCallController.done(c.id);
    await fetch(route.url, {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    await load();
}

const age = (c: Call): string => {
    const s = c.created_at
        ? Math.max(
              0,
              Math.round((now.value - new Date(c.created_at).getTime()) / 1000),
          )
        : 0;
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
};
const ageSeconds = (c: Call): number =>
    c.created_at ? (now.value - new Date(c.created_at).getTime()) / 1000 : 0;
const visible = computed(
    () => can('pos.use') && calls.value.length > 0 && !hidden.value,
);

onMounted(() => {
    if (!can('pos.use')) return;
    void load();
    timer = setInterval(load, 10000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div
        v-if="visible"
        class="fixed right-3 bottom-3 z-50 flex w-80 max-w-[calc(100vw-1.5rem)] flex-col gap-2"
        role="alert"
        aria-live="assertive"
    >
        <div
            class="flex items-center justify-between rounded-lg bg-foreground px-3 py-1.5 text-xs font-semibold text-background"
        >
            <span class="flex items-center gap-1"
                ><BellRing class="size-3.5" /> {{ calls.length }} guest
                call(s)</span
            >
            <button type="button" aria-label="Hide" @click="hidden = true">
                <X class="size-3.5" />
            </button>
        </div>
        <div
            v-for="c in calls"
            :key="c.id"
            class="flex flex-col gap-2 rounded-xl border-2 bg-background p-3 shadow-lg"
            :class="
                c.status === 'accepted'
                    ? 'border-sky-400'
                    : ageSeconds(c) > 180
                      ? 'animate-pulse border-red-500'
                      : 'border-orange-500'
            "
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="text-lg font-bold">
                        {{ c.table }} · {{ c.type_label }}
                    </div>
                    <div v-if="c.note" class="text-sm">{{ c.note }}</div>
                    <div v-if="c.accepted_by" class="text-xs text-sky-700">
                        {{ c.accepted_by }} is going
                    </div>
                    <div
                        v-else-if="ageSeconds(c) > 180"
                        class="text-xs font-bold text-red-600"
                    >
                        Nobody accepted for 3 min — manager please check
                    </div>
                </div>
                <span class="font-mono text-sm tabular-nums">{{ age(c) }}</span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-if="c.status === 'sent'"
                    type="button"
                    class="flex h-10 items-center justify-center gap-1 rounded-md bg-orange-600 text-sm font-semibold text-white"
                    @click="act(c, 'accept')"
                >
                    <BellRing class="size-4" /> Accept
                </button>
                <button
                    type="button"
                    class="flex h-10 items-center justify-center gap-1 rounded-md border text-sm font-semibold"
                    :class="c.status === 'sent' ? '' : 'col-span-2'"
                    @click="act(c, 'done')"
                >
                    <Check class="size-4" /> Done
                </button>
            </div>
        </div>
    </div>
</template>
