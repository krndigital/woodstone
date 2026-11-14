<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Printer, RefreshCw } from '@lucide/vue';
import QRCode from 'qrcode';
import { onMounted, ref, watch } from 'vue';
import FloorController from '@/actions/App/Http/Controllers/Floor/FloorController';
import { Button } from '@/components/ui/button';
import { index as floorIndex } from '@/routes/floor';

type Card = { id: number; label: string; url: string };
const props = defineProps<{ tables: Card[]; restaurant: string }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tables', href: floorIndex() }] },
});

const images = ref<Record<number, string>>({});
async function render(): Promise<void> {
    const out: Record<number, string> = {};
    for (const t of props.tables) {
        out[t.id] = await QRCode.toDataURL(t.url, {
            margin: 1,
            width: 360,
            errorCorrectionLevel: 'M',
        });
    }
    images.value = out;
}
onMounted(render);
watch(() => props.tables, render);

function printAll(): void {
    window.print();
}

function regenerate(t: Card): void {
    if (
        !window.confirm(
            `Make a new QR for ${t.label}? The old printed card will stop working.`,
        )
    )
        return;
    router.visit(FloorController.regenerateQr(t.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="QR table cards" />
    <div class="flex flex-col gap-4 p-4 print:p-0">
        <div class="flex items-center gap-3 print:hidden">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    QR table cards
                </h1>
                <p class="text-sm text-muted-foreground">
                    Guests scan to see the menu and call a waiter. Print and
                    place one on each table.
                </p>
            </div>
            <Button class="ml-auto h-11" @click="printAll"
                ><Printer class="size-4" /> Print all</Button
            >
        </div>
        <div
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 print:grid-cols-3"
        >
            <div
                v-for="t in tables"
                :key="t.id"
                class="flex break-inside-avoid flex-col items-center gap-2 rounded-xl border-2 border-dashed p-4 text-center"
            >
                <div
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ restaurant }}
                </div>
                <div class="text-2xl font-bold">{{ t.label }}</div>
                <img
                    v-if="images[t.id]"
                    :src="images[t.id]"
                    :alt="`QR code for ${t.label}`"
                    class="size-40"
                />
                <div class="text-xs">Scan for menu · Call waiter</div>
                <button
                    type="button"
                    class="mt-1 flex h-8 items-center gap-1 rounded-md border px-2 text-xs print:hidden"
                    @click="regenerate(t)"
                >
                    <RefreshCw class="size-3" /> New QR
                </button>
            </div>
        </div>
    </div>
</template>
