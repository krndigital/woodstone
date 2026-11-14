<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Delete } from '@lucide/vue';
import { ref } from 'vue';
import AttendanceController from '@/actions/App/Http/Controllers/Staff/AttendanceController';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index as attendanceIndex } from '@/routes/attendance';

type Person = {
    id: number;
    name: string;
    position: string;
    in: string | null;
    out: string | null;
    status: string | null;
};

defineProps<{ staff: Person[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Attendance', href: attendanceIndex() }] },
});

const picked = ref<Person | null>(null);
const form = useForm({ staff_id: 0, pin: '' });
const time = (iso: string | null): string =>
    iso
        ? new Date(iso).toLocaleTimeString('en-GB', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '';

function pick(p: Person): void {
    form.reset();
    form.clearErrors();
    form.staff_id = p.id;
    picked.value = p;
}
function key(d: string): void {
    if (form.pin.length < 4) form.pin += d;
    if (form.pin.length === 4) {
        form.submit(AttendanceController.punch(), {
            preserveScroll: true,
            onSuccess: () => (picked.value = null),
            onFinish: () => (form.pin = ''),
        });
    }
}
</script>

<template>
    <Head title="Attendance" />
    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Attendance</h1>
            <p class="text-sm text-muted-foreground">
                Tap your name, then enter your 4-digit PIN to check in or out.
            </p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <button
                v-for="p in staff"
                :key="p.id"
                type="button"
                class="flex min-h-28 flex-col items-start gap-1 rounded-xl border-2 p-4 text-left active:scale-95"
                :class="
                    p.out
                        ? 'border-slate-300 bg-slate-50 dark:bg-slate-900'
                        : p.in
                          ? 'border-green-400 bg-green-50 dark:bg-green-950/40'
                          : ''
                "
                @click="pick(p)"
            >
                <span class="text-lg font-bold">{{ p.name }}</span>
                <span class="text-xs text-muted-foreground">{{
                    p.position
                }}</span>
                <span v-if="p.in" class="mt-auto text-sm"
                    >In {{ time(p.in)
                    }}<span
                        v-if="p.status === 'late'"
                        class="font-bold text-amber-700"
                    >
                        late</span
                    >{{ p.out ? ` · Out ${time(p.out)}` : '' }}</span
                >
                <span v-else class="mt-auto text-sm text-muted-foreground"
                    >Not in yet</span
                >
            </button>
            <p
                v-if="staff.length === 0"
                class="col-span-full py-10 text-center text-muted-foreground"
            >
                No staff with an attendance PIN. Set PINs on the Staff page.
            </p>
        </div>
    </div>

    <Dialog :open="picked !== null" @update:open="(v) => !v && (picked = null)">
        <DialogContent v-if="picked" class="max-w-xs">
            <DialogHeader
                ><DialogTitle>{{ picked.name }}</DialogTitle
                ><DialogDescription
                    >{{ picked.in && !picked.out ? 'Check out' : 'Check in' }} —
                    enter PIN</DialogDescription
                ></DialogHeader
            >
            <div class="flex justify-center gap-3 py-2">
                <span
                    v-for="i in 4"
                    :key="i"
                    class="size-4 rounded-full border-2"
                    :class="form.pin.length >= i ? 'bg-foreground' : ''"
                />
            </div>
            <InputError class="text-center" :message="form.errors.pin" />
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="d in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                    :key="d"
                    type="button"
                    class="h-14 rounded-lg border text-xl font-bold"
                    :disabled="form.processing"
                    @click="key(d)"
                >
                    {{ d }}
                </button>
                <span />
                <button
                    type="button"
                    class="h-14 rounded-lg border text-xl font-bold"
                    :disabled="form.processing"
                    @click="key('0')"
                >
                    0
                </button>
                <button
                    type="button"
                    class="flex h-14 items-center justify-center rounded-lg border"
                    aria-label="Delete"
                    @click="form.pin = form.pin.slice(0, -1)"
                >
                    <Delete class="size-5" />
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
