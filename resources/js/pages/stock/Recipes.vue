<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import RecipeController from '@/actions/App/Http/Controllers/Stock/RecipeController';
import InputError from '@/components/InputError.vue';
import StockTabs from '@/components/stock/StockTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { rs, toPaisa } from '@/lib/money';
import { index as recipesIndex } from '@/routes/stock/recipes';

type MenuItem = {
    id: number;
    code: string;
    name: string;
    price: string;
    cost: string;
    margin: number | null;
    lines: {
        stock_item_id: number;
        name: string;
        unit: string;
        quantity: string;
    }[];
};
type StockItem = { id: number; name: string; unit: string; avg_cost: string };

const props = defineProps<{ menuItems: MenuItem[]; stockItems: StockItem[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stock', href: recipesIndex() }] },
});

const q = ref('');
const shown = computed(() =>
    props.menuItems.filter((m) =>
        (m.name + m.code).toLowerCase().includes(q.value.trim().toLowerCase()),
    ),
);
const selectedId = ref<number | null>(props.menuItems[0]?.id ?? null);
const selected = computed(
    () => props.menuItems.find((m) => m.id === selectedId.value) ?? null,
);

type Line = { stock_item_id: number | null; quantity: string };
const form = useForm({ lines: [] as Line[] });
function pick(m: MenuItem): void {
    selectedId.value = m.id;
    form.clearErrors();
    form.lines = m.lines.map((l) => ({
        stock_item_id: l.stock_item_id,
        quantity: String(Number(l.quantity)),
    }));
}
if (selected.value) pick(selected.value);

const stock = (id: number | null): StockItem | undefined =>
    props.stockItems.find((s) => s.id === id);
const previewCost = computed(() =>
    form.lines.reduce(
        (a, l) =>
            a +
            Number(l.quantity || 0) *
                Number(stock(l.stock_item_id)?.avg_cost ?? 0),
        0,
    ),
);
const previewMargin = computed(() => {
    const price = toPaisa(selected.value?.price ?? '0') / 100;
    return price > 0
        ? Math.floor(((price - previewCost.value) / price) * 100)
        : null;
});
function errorFor(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}
function save(): void {
    if (!selected.value) return;
    form.submit(RecipeController.update(selected.value.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Recipes" />
    <div class="flex flex-col gap-4 p-4">
        <StockTabs current="recipes" />
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Recipes & food cost
            </h1>
            <p class="text-sm text-muted-foreground">
                Ingredients per plate. When a bill is paid, these are taken out
                of stock.
            </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-[360px_1fr]">
            <div class="flex flex-col gap-2">
                <Input
                    v-model="q"
                    class="h-10"
                    placeholder="Search menu item"
                />
                <div
                    class="flex max-h-[65vh] flex-col overflow-y-auto rounded-xl border"
                >
                    <button
                        v-for="m in shown"
                        :key="m.id"
                        type="button"
                        class="flex items-center justify-between gap-2 border-b px-3 py-2 text-left text-sm last:border-0"
                        :class="
                            selectedId === m.id
                                ? 'bg-muted'
                                : 'hover:bg-muted/50'
                        "
                        @click="pick(m)"
                    >
                        <span
                            ><span class="font-semibold">{{ m.name }}</span
                            ><span
                                class="block text-xs text-muted-foreground"
                                >{{
                                    m.lines.length
                                        ? `${m.lines.length} ingredients`
                                        : 'No recipe'
                                }}</span
                            ></span
                        >
                        <span class="text-right text-xs tabular-nums">
                            {{ rs(m.price) }}
                            <span
                                v-if="m.margin !== null"
                                class="block font-semibold"
                                :class="
                                    m.margin < 50
                                        ? 'text-red-600'
                                        : 'text-green-700 dark:text-green-400'
                                "
                                >{{ m.margin }}% margin</span
                            >
                        </span>
                    </button>
                </div>
            </div>

            <section
                v-if="selected"
                class="flex flex-col gap-3 rounded-xl border p-4"
            >
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h2 class="text-lg font-bold">{{ selected.name }}</h2>
                    <span class="text-sm"
                        >Price {{ rs(selected.price) }} · cost
                        <b>Rs {{ previewCost.toFixed(2) }}</b
                        ><template v-if="previewMargin !== null">
                            · margin <b>{{ previewMargin }}%</b></template
                        ></span
                    >
                </div>
                <div
                    v-for="(l, i) in form.lines"
                    :key="i"
                    class="grid grid-cols-[1fr_120px_36px] items-start gap-2"
                >
                    <select
                        v-model="l.stock_item_id"
                        class="h-10 rounded-lg border bg-background px-2 text-sm"
                    >
                        <option :value="null" disabled>Ingredient</option>
                        <option
                            v-for="s in stockItems"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.name }} ({{ s.unit }})
                        </option>
                    </select>
                    <div class="flex items-center gap-1">
                        <Input v-model="l.quantity" inputmode="decimal" /><span
                            class="text-xs text-muted-foreground"
                            >{{ stock(l.stock_item_id)?.unit }}</span
                        >
                    </div>
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-md border"
                        @click="form.lines.splice(i, 1)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                    <p
                        v-if="
                            errorFor(`lines.${i}.stock_item_id`) ||
                            errorFor(`lines.${i}.quantity`)
                        "
                        class="col-span-3 -mt-1 text-xs text-red-600"
                    >
                        {{
                            errorFor(`lines.${i}.stock_item_id`) ||
                            errorFor(`lines.${i}.quantity`)
                        }}
                    </p>
                </div>
                <p
                    v-if="form.lines.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No ingredients yet.
                </p>
                <InputError :message="form.errors.lines" />
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10"
                        :disabled="stockItems.length === 0"
                        @click="
                            form.lines.push({
                                stock_item_id: null,
                                quantity: '',
                            })
                        "
                        ><Plus class="size-4" /> Add ingredient</Button
                    >
                    <Button
                        type="button"
                        class="ml-auto h-10"
                        :disabled="form.processing"
                        @click="save"
                        >Save recipe</Button
                    >
                </div>
            </section>
        </div>
    </div>
</template>
