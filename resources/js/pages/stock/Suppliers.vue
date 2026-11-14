<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import SupplierController from '@/actions/App/Http/Controllers/Stock/SupplierController';
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
import { index as suppliersIndex } from '@/routes/stock/suppliers';

type Supplier = {
    id: number;
    name: string;
    phone: string | null;
    pan: string | null;
    address: string | null;
    is_active: boolean;
    purchases: number;
    owed: string;
};

defineProps<{ suppliers: Supplier[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stock', href: suppliersIndex() }] },
});

const open = ref(false);
const current = ref<Supplier | null>(null);
const form = useForm({
    name: '',
    phone: '',
    pan: '',
    address: '',
    is_active: true,
});
function edit(s: Supplier | null): void {
    current.value = s;
    form.clearErrors();
    form.name = s?.name ?? '';
    form.phone = s?.phone ?? '';
    form.pan = s?.pan ?? '';
    form.address = s?.address ?? '';
    form.is_active = s?.is_active ?? true;
    open.value = true;
}
function save(): void {
    form.submit(
        current.value
            ? SupplierController.update(current.value.id)
            : SupplierController.store(),
        { preserveScroll: true, onSuccess: () => (open.value = false) },
    );
}
</script>

<template>
    <Head title="Suppliers" />
    <div class="flex flex-col gap-4 p-4">
        <StockTabs current="suppliers" />
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Suppliers</h1>
                <p class="text-sm text-muted-foreground">
                    Owed = purchase amounts not paid at the time. Supplier
                    payments come with the cash book.
                </p>
            </div>
            <Button class="ml-auto h-11" @click="edit(null)"
                ><Plus class="size-4" /> Add supplier</Button
            >
        </div>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Supplier</th>
                        <th class="p-3">PAN</th>
                        <th class="p-3 text-right">Purchases</th>
                        <th class="p-3 text-right">Owed</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="s in suppliers"
                        :key="s.id"
                        class="border-t"
                        :class="s.is_active ? '' : 'opacity-50'"
                    >
                        <td class="p-3">
                            <div class="font-semibold">{{ s.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ s.phone ?? '—'
                                }}{{ s.address ? ` · ${s.address}` : '' }}
                            </div>
                        </td>
                        <td class="p-3 tabular-nums">{{ s.pan ?? '—' }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ s.purchases }}
                        </td>
                        <td
                            class="p-3 text-right font-bold tabular-nums"
                            :class="
                                toPaisa(s.owed) > 0
                                    ? 'text-amber-700 dark:text-amber-400'
                                    : ''
                            "
                        >
                            {{ rs(s.owed) }}
                        </td>
                        <td class="p-3 text-right">
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-md border"
                                aria-label="Edit"
                                @click="edit(s)"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="suppliers.length === 0">
                        <td
                            colspan="5"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No suppliers yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Sheet v-model:open="open">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <SheetHeader
                ><SheetTitle>{{
                    current ? 'Edit supplier' : 'Add supplier'
                }}</SheetTitle
                ><SheetDescription
                    >PAN is 9 digits.</SheetDescription
                ></SheetHeader
            >
            <form class="flex flex-col gap-3 px-4" @submit.prevent="save">
                <div class="grid gap-2">
                    <Label for="sn">Name</Label
                    ><Input id="sn" v-model="form.name" /><InputError
                        :message="form.errors.name"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="sp">Phone</Label
                    ><Input
                        id="sp"
                        v-model="form.phone"
                        inputmode="tel"
                    /><InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="span">PAN</Label
                    ><Input
                        id="span"
                        v-model="form.pan"
                        inputmode="numeric"
                    /><InputError :message="form.errors.pan" />
                </div>
                <div class="grid gap-2">
                    <Label for="sa">Address</Label
                    ><Input id="sa" v-model="form.address" />
                </div>
                <label v-if="current" class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4"
                    />
                    Active</label
                >
                <Button type="submit" class="h-12" :disabled="form.processing"
                    >Save</Button
                >
            </form>
        </SheetContent>
    </Sheet>
</template>
