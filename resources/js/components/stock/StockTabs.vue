<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as items } from '@/routes/stock/items';
import { index as orders } from '@/routes/stock/orders';
import { index as purchases } from '@/routes/stock/purchases';
import { index as recipes } from '@/routes/stock/recipes';
import { index as suppliers } from '@/routes/stock/suppliers';

defineProps<{
    current: 'items' | 'purchases' | 'orders' | 'suppliers' | 'recipes';
}>();

const tabs = [
    { key: 'items', label: 'Stock', href: items().url },
    { key: 'purchases', label: 'Purchases', href: purchases().url },
    { key: 'orders', label: 'Purchase orders', href: orders().url },
    { key: 'suppliers', label: 'Suppliers', href: suppliers().url },
    { key: 'recipes', label: 'Recipes & food cost', href: recipes().url },
] as const;
</script>

<template>
    <nav class="flex gap-1 overflow-x-auto rounded-lg bg-muted p-1">
        <Link
            v-for="t in tabs"
            :key="t.key"
            :href="t.href"
            class="flex h-10 shrink-0 items-center rounded-md px-4 text-sm font-semibold"
            :class="
                current === t.key
                    ? 'bg-background shadow-sm'
                    : 'text-muted-foreground'
            "
        >
            {{ t.label }}
        </Link>
    </nav>
</template>
