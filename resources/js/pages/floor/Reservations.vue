<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReservationController from '@/actions/App/Http/Controllers/Floor/ReservationController';
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
import { rs, toPaisa } from '@/lib/money';
import { index as floorIndex } from '@/routes/floor';
import { index as reservationsIndex } from '@/routes/floor/reservations';

type Booking = {
    id: number;
    table: string;
    name: string;
    phone: string | null;
    reserved_at: string;
    bs: string;
    guests: number;
    advance: string;
    advance_used: string;
    status: 'booked' | 'seated' | 'cancelled' | 'no_show';
    order_id: number | null;
    note: string | null;
};

const props = defineProps<{
    reservations: Booking[];
    tables: { id: number; label: string; seats: number }[];
    can: { money: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Bookings', href: reservationsIndex() }] },
});

const upcoming = computed(() =>
    props.reservations.filter((r) => r.status === 'booked'),
);
const past = computed(() =>
    props.reservations.filter((r) => r.status !== 'booked'),
);
const STATUS: Record<Booking['status'], string> = {
    booked: 'Booked',
    seated: 'Seated',
    cancelled: 'Cancelled',
    no_show: 'No-show',
};

const open = ref(false);
const form = useForm({
    dining_table_id: null as number | null,
    name: '',
    phone: '',
    reserved_at: '',
    guests: 2,
    advance: '',
    advance_method: 'cash',
    note: '',
});
function start(): void {
    form.reset();
    form.clearErrors();
    open.value = true;
}
function save(): void {
    form.transform((d) => ({
        ...d,
        advance_method: toPaisa(d.advance) > 0 ? d.advance_method : null,
    })).submit(ReservationController.store(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
function seat(r: Booking): void {
    router.visit(ReservationController.seat(r.id));
}
function close(r: Booking, status: 'cancelled' | 'no_show'): void {
    const left = toPaisa(r.advance) - toPaisa(r.advance_used);
    let refund: string | null = null;
    if (left > 0 && props.can.money) {
        refund = window.confirm(
            `Refund the advance of ${rs(left)} in cash? Cancel = keep it.`,
        )
            ? 'cash'
            : null;
    } else if (
        !window.confirm(`Mark ${r.name} as ${STATUS[status].toLowerCase()}?`)
    ) {
        return;
    }
    router.visit(ReservationController.close(r.id), {
        data: { status, refund_method: refund },
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Bookings" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <Link
                :href="floorIndex().url"
                class="flex size-11 items-center justify-center rounded-lg border"
                aria-label="Back to tables"
                ><ArrowLeft class="size-5"
            /></Link>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Bookings</h1>
                <p class="text-sm text-muted-foreground">
                    Today's bookings mark the table as reserved. Seat opens the
                    order.
                </p>
            </div>
            <Button class="ml-auto h-11" @click="start"
                ><Plus class="size-4" /> New booking</Button
            >
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="r in upcoming"
                :key="r.id"
                class="flex flex-col gap-2 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-lg font-bold">{{ r.name }}</div>
                        <div class="text-sm text-muted-foreground">
                            {{ r.table }} · {{ r.guests }} guests{{
                                r.phone ? ` · ${r.phone}` : ''
                            }}
                        </div>
                    </div>
                    <div class="text-right text-sm">
                        <div class="font-bold">
                            {{ r.reserved_at.slice(11) }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ r.bs }}
                        </div>
                    </div>
                </div>
                <div v-if="toPaisa(r.advance)" class="text-sm">
                    Advance {{ rs(r.advance) }}
                </div>
                <div v-if="r.note" class="text-xs text-muted-foreground">
                    {{ r.note }}
                </div>
                <div class="mt-auto flex flex-wrap gap-1.5">
                    <Button class="h-10" @click="seat(r)">Seat now</Button>
                    <Button
                        variant="outline"
                        class="h-10"
                        @click="close(r, 'cancelled')"
                        >Cancel</Button
                    >
                    <Button
                        variant="outline"
                        class="h-10"
                        @click="close(r, 'no_show')"
                        >No-show</Button
                    >
                </div>
            </article>
            <p
                v-if="upcoming.length === 0"
                class="col-span-full py-10 text-center text-muted-foreground"
            >
                No upcoming bookings.
            </p>
        </div>

        <section v-if="past.length" class="flex flex-col gap-1 text-sm">
            <h2
                class="text-sm font-bold tracking-wide text-muted-foreground uppercase"
            >
                Recent
            </h2>
            <div
                v-for="r in past"
                :key="r.id"
                class="flex justify-between rounded-lg border px-3 py-2"
            >
                <span>{{ r.reserved_at }} · {{ r.name }} · {{ r.table }}</span
                ><span class="font-semibold">{{ STATUS[r.status] }}</span>
            </div>
        </section>
    </div>

    <Sheet v-model:open="open">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <SheetHeader
                ><SheetTitle>New booking</SheetTitle
                ><SheetDescription
                    >An advance is held and used on the bill.</SheetDescription
                ></SheetHeader
            >
            <form class="flex flex-col gap-3 px-4 pb-6" @submit.prevent="save">
                <div class="grid gap-2">
                    <Label>Table / cabin</Label>
                    <select
                        v-model="form.dining_table_id"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option :value="null" disabled>Choose</option>
                        <option v-for="t in tables" :key="t.id" :value="t.id">
                            {{ t.label }} ({{ t.seats }} seats)
                        </option>
                    </select>
                    <InputError :message="form.errors.dining_table_id" />
                </div>
                <div class="grid gap-2">
                    <Label>Name</Label><Input v-model="form.name" /><InputError
                        :message="form.errors.name"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Phone</Label
                    ><Input v-model="form.phone" inputmode="tel" /><InputError
                        :message="form.errors.phone"
                    />
                </div>
                <div class="grid grid-cols-[1fr_90px] gap-2">
                    <div class="grid gap-2">
                        <Label>Date and time</Label
                        ><Input
                            v-model="form.reserved_at"
                            type="datetime-local"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Guests</Label
                        ><Input
                            v-model.number="form.guests"
                            type="number"
                            min="1"
                        />
                    </div>
                </div>
                <InputError
                    :message="form.errors.reserved_at || form.errors.guests"
                />
                <div v-if="can.money" class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label>Advance (Rs)</Label
                        ><Input
                            v-model="form.advance"
                            inputmode="decimal"
                            placeholder="0"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Paid by</Label>
                        <select
                            v-model="form.advance_method"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="cash">Cash</option>
                            <option value="qr">QR</option>
                            <option value="card">Card</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                </div>
                <InputError
                    :message="form.errors.advance || form.errors.advance_method"
                />
                <Input
                    v-model="form.note"
                    placeholder="Note (birthday cake, high chair…)"
                />
                <Button type="submit" class="h-12" :disabled="form.processing"
                    >Save booking</Button
                >
            </form>
        </SheetContent>
    </Sheet>
</template>
