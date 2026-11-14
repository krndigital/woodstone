<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, HandCoins, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import StaffController from '@/actions/App/Http/Controllers/Staff/StaffController';
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
import { index as staffIndex, payroll } from '@/routes/staff';

type Summary = {
    working: number;
    present: number;
    late: number;
    leave: number;
    absent: number;
};
type Member = {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    position: string;
    joined_on: string;
    salary_type: 'monthly' | 'daily';
    salary_amount: string;
    weekly_off: number | null;
    shift_start: string | null;
    shift_end: string | null;
    has_pin: boolean;
    has_citizenship: boolean;
    user_id: number | null;
    username: string | null;
    is_active: boolean;
    advance_due: string;
    this_month: Summary | null;
};
type Leave = {
    id: number;
    staff: string;
    from: string;
    to: string;
    reason: string;
    status: string;
};

const props = defineProps<{
    staff: Member[];
    users: { id: number; name: string; username: string }[];
    accounts: { id: number; name: string }[];
    leaves: Leave[];
    today: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Staff', href: staffIndex() }] },
});

const DAYS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];
const panel = ref<'edit' | 'advance' | 'leave' | 'mark' | null>(null);
const current = ref<Member | null>(null);

const form = useForm({
    name: '',
    phone: '',
    address: '',
    citizenship_no: '',
    position: '',
    joined_on: props.today,
    salary_type: 'monthly' as 'monthly' | 'daily',
    salary_amount: '',
    weekly_off: null as number | null,
    shift_start: '',
    shift_end: '',
    attendance_pin: '',
    user_id: null as number | null,
    is_active: true,
});
function edit(m: Member | null): void {
    current.value = m;
    form.reset();
    form.clearErrors();
    if (m) {
        Object.assign(form, {
            name: m.name,
            phone: m.phone ?? '',
            address: m.address ?? '',
            position: m.position,
            joined_on: m.joined_on,
            salary_type: m.salary_type,
            salary_amount: m.salary_amount,
            weekly_off: m.weekly_off,
            shift_start: m.shift_start ?? '',
            shift_end: m.shift_end ?? '',
            user_id: m.user_id,
            is_active: m.is_active,
        });
    }
    panel.value = 'edit';
}
function save(): void {
    form.submit(
        current.value
            ? StaffController.update(current.value.id)
            : StaffController.store(),
        { preserveScroll: true, onSuccess: () => (panel.value = null) },
    );
}

const advanceForm = useForm({
    amount: '',
    account_id: props.accounts[0]?.id ?? null,
    note: '',
});
function startAdvance(m: Member): void {
    current.value = m;
    advanceForm.reset();
    advanceForm.clearErrors();
    panel.value = 'advance';
}
function giveAdvance(): void {
    if (current.value)
        advanceForm.submit(StaffController.advance(current.value.id), {
            preserveScroll: true,
            onSuccess: () => (panel.value = null),
        });
}

const leaveForm = useForm({
    staff_id: null as number | null,
    from_date: props.today,
    to_date: props.today,
    reason: '',
});
function saveLeave(): void {
    leaveForm.submit(StaffController.storeLeave(), {
        preserveScroll: true,
        onSuccess: () => leaveForm.reset('reason'),
    });
}
function decide(l: Leave, status: 'approved' | 'rejected'): void {
    router.visit(StaffController.decideLeave(l.id), {
        data: { status },
        preserveScroll: true,
    });
}

const markForm = useForm({
    work_date: props.today,
    status: 'present',
    note: '',
});
function startMark(m: Member): void {
    current.value = m;
    markForm.reset();
    panel.value = 'mark';
}
function mark(): void {
    if (current.value)
        markForm.submit(StaffController.markAttendance(current.value.id), {
            preserveScroll: true,
            onSuccess: () => (panel.value = null),
        });
}
</script>

<template>
    <Head title="Staff" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Staff</h1>
                <p class="text-sm text-muted-foreground">
                    This month so far: present / late / leave / absent
                </p>
            </div>
            <div class="ml-auto flex gap-2">
                <Link
                    :href="payroll().url"
                    class="flex h-11 items-center gap-2 rounded-md border px-4 text-sm font-semibold"
                    ><CalendarDays class="size-4" /> Salary sheet</Link
                >
                <Button variant="outline" class="h-11" @click="panel = 'leave'"
                    >Leave</Button
                >
                <Button class="h-11" @click="edit(null)"
                    ><Plus class="size-4" /> Add staff</Button
                >
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Salary</th>
                        <th class="p-3">Shift</th>
                        <th class="p-3 text-center">This month</th>
                        <th class="p-3 text-right">Advance due</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="m in staff"
                        :key="m.id"
                        class="border-t"
                        :class="m.is_active ? '' : 'opacity-50'"
                    >
                        <td class="p-3">
                            <div class="font-semibold">{{ m.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ m.position
                                }}{{ m.username ? ` · login ${m.username}` : ''
                                }}{{ m.has_pin ? '' : ' · no attendance PIN' }}
                            </div>
                        </td>
                        <td class="p-3 tabular-nums">
                            {{ rs(m.salary_amount) }} /
                            {{ m.salary_type === 'daily' ? 'day' : 'month' }}
                        </td>
                        <td class="p-3 text-xs">
                            {{
                                m.shift_start
                                    ? `${m.shift_start}–${m.shift_end ?? ''}`
                                    : '—'
                            }}
                            <div
                                v-if="m.weekly_off !== null"
                                class="text-muted-foreground"
                            >
                                Off {{ DAYS[m.weekly_off] }}
                            </div>
                        </td>
                        <td class="p-3 text-center tabular-nums">
                            <template v-if="m.this_month">
                                <span
                                    class="text-green-700 dark:text-green-400"
                                    >{{ m.this_month.present }}</span
                                >
                                /
                                <span class="text-amber-700">{{
                                    m.this_month.late
                                }}</span>
                                /
                                <span class="text-sky-700">{{
                                    m.this_month.leave
                                }}</span>
                                /
                                <span class="text-red-600">{{
                                    m.this_month.absent
                                }}</span>
                            </template>
                        </td>
                        <td
                            class="p-3 text-right tabular-nums"
                            :class="
                                toPaisa(m.advance_due)
                                    ? 'font-semibold text-amber-700 dark:text-amber-400'
                                    : ''
                            "
                        >
                            {{ rs(m.advance_due) }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1.5">
                                <button
                                    type="button"
                                    class="h-9 rounded-md border px-2 text-xs font-semibold"
                                    @click="startMark(m)"
                                >
                                    Mark day
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center gap-1 rounded-md border px-2 text-xs font-semibold"
                                    @click="startAdvance(m)"
                                >
                                    <HandCoins class="size-3.5" /> Advance
                                </button>
                                <button
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-md border"
                                    aria-label="Edit"
                                    @click="edit(m)"
                                >
                                    <Pencil class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="staff.length === 0">
                        <td
                            colspan="6"
                            class="p-10 text-center text-muted-foreground"
                        >
                            No staff yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section v-if="leaves.length" class="flex flex-col gap-2">
            <h2
                class="text-sm font-bold tracking-wide text-muted-foreground uppercase"
            >
                Leave requests
            </h2>
            <div
                v-for="l in leaves"
                :key="l.id"
                class="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm"
            >
                <b>{{ l.staff }}</b> {{ l.from }} → {{ l.to }} · {{ l.reason }}
                <span
                    class="ml-auto rounded px-2 py-0.5 text-xs font-bold"
                    :class="
                        l.status === 'approved'
                            ? 'bg-green-100 text-green-900'
                            : l.status === 'rejected'
                              ? 'bg-red-100 text-red-900'
                              : 'bg-amber-100 text-amber-900'
                    "
                    >{{ l.status }}</span
                >
                <template v-if="l.status === 'pending'">
                    <button
                        type="button"
                        class="h-8 rounded-md border px-2 text-xs"
                        @click="decide(l, 'approved')"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="h-8 rounded-md border px-2 text-xs"
                        @click="decide(l, 'rejected')"
                    >
                        Reject
                    </button>
                </template>
            </div>
        </section>
    </div>

    <Sheet :open="panel !== null" @update:open="(v) => !v && (panel = null)">
        <SheetContent class="flex flex-col gap-4 overflow-y-auto">
            <template v-if="panel === 'edit'">
                <SheetHeader
                    ><SheetTitle>{{
                        current ? 'Edit staff' : 'Add staff'
                    }}</SheetTitle
                    ><SheetDescription
                        >Citizenship number is stored encrypted and never shown
                        again.</SheetDescription
                    ></SheetHeader
                >
                <form
                    class="flex flex-col gap-3 px-4 pb-6"
                    @submit.prevent="save"
                >
                    <div class="grid gap-2">
                        <Label>Name</Label
                        ><Input v-model="form.name" /><InputError
                            :message="form.errors.name"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="grid gap-2">
                            <Label>Position</Label
                            ><Input
                                v-model="form.position"
                                placeholder="Waiter, Cook…"
                            /><InputError :message="form.errors.position" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Joined on</Label
                            ><Input
                                v-model="form.joined_on"
                                type="date"
                            /><InputError :message="form.errors.joined_on" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="grid gap-2">
                            <Label>Phone</Label
                            ><Input
                                v-model="form.phone"
                                inputmode="tel"
                            /><InputError :message="form.errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Address</Label
                            ><Input v-model="form.address" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label
                            >Citizenship no.
                            {{
                                current?.has_citizenship
                                    ? '(saved — type to replace)'
                                    : ''
                            }}</Label
                        ><Input
                            v-model="form.citizenship_no"
                            autocomplete="off"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="grid gap-2">
                            <Label>Salary type</Label>
                            <select
                                v-model="form.salary_type"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            >
                                <option value="monthly">Monthly</option>
                                <option value="daily">Daily</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Amount (Rs)</Label
                            ><Input
                                v-model="form.salary_amount"
                                inputmode="decimal"
                            /><InputError
                                :message="form.errors.salary_amount"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="grid gap-2">
                            <Label>Shift start</Label
                            ><Input v-model="form.shift_start" type="time" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Shift end</Label
                            ><Input v-model="form.shift_end" type="time" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Weekly off</Label>
                            <select
                                v-model="form.weekly_off"
                                class="h-10 rounded-lg border bg-background px-2 text-sm"
                            >
                                <option :value="null">None</option>
                                <option
                                    v-for="(d, i) in DAYS"
                                    :key="d"
                                    :value="i"
                                >
                                    {{ d }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <InputError
                        :message="
                            form.errors.shift_start || form.errors.shift_end
                        "
                    />
                    <div class="grid gap-2">
                        <Label
                            >Attendance PIN (4 digits)
                            {{
                                current?.has_pin ? '— leave blank to keep' : ''
                            }}</Label
                        ><Input
                            v-model="form.attendance_pin"
                            inputmode="numeric"
                            maxlength="4"
                            autocomplete="off"
                        /><InputError :message="form.errors.attendance_pin" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Linked login (for sales per waiter)</Label>
                        <select
                            v-model="form.user_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option :value="null">None</option>
                            <option
                                v-for="u in users"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }} ({{ u.username }})
                            </option>
                        </select>
                        <InputError :message="form.errors.user_id" />
                    </div>
                    <label
                        v-if="current"
                        class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4"
                        />
                        Still working here</label
                    >
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="form.processing"
                        >Save</Button
                    >
                </form>
            </template>
            <template v-else-if="panel === 'advance' && current">
                <SheetHeader
                    ><SheetTitle>Advance to {{ current.name }}</SheetTitle
                    ><SheetDescription
                        >Deducted from the next salary.</SheetDescription
                    ></SheetHeader
                >
                <form
                    class="flex flex-col gap-3 px-4"
                    @submit.prevent="giveAdvance"
                >
                    <Input
                        v-model="advanceForm.amount"
                        inputmode="decimal"
                        placeholder="Amount (Rs)"
                    />
                    <InputError :message="advanceForm.errors.amount" />
                    <select
                        v-model="advanceForm.account_id"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option v-for="a in accounts" :key="a.id" :value="a.id">
                            {{ a.name }}
                        </option>
                    </select>
                    <Input
                        v-model="advanceForm.note"
                        placeholder="Note (optional)"
                    />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="advanceForm.processing"
                        >Give advance</Button
                    >
                </form>
            </template>
            <template v-else-if="panel === 'leave'">
                <SheetHeader
                    ><SheetTitle>Leave request</SheetTitle
                    ><SheetDescription
                        >Approved leave is not counted as
                        absent.</SheetDescription
                    ></SheetHeader
                >
                <form
                    class="flex flex-col gap-3 px-4"
                    @submit.prevent="saveLeave"
                >
                    <select
                        v-model="leaveForm.staff_id"
                        class="h-10 rounded-lg border bg-background px-3 text-sm"
                    >
                        <option :value="null" disabled>Staff</option>
                        <option
                            v-for="m in staff.filter((x) => x.is_active)"
                            :key="m.id"
                            :value="m.id"
                        >
                            {{ m.name }}
                        </option>
                    </select>
                    <InputError :message="leaveForm.errors.staff_id" />
                    <div class="grid grid-cols-2 gap-2">
                        <Input
                            v-model="leaveForm.from_date"
                            type="date"
                        /><Input v-model="leaveForm.to_date" type="date" />
                    </div>
                    <InputError :message="leaveForm.errors.to_date" />
                    <Input v-model="leaveForm.reason" placeholder="Reason" />
                    <InputError :message="leaveForm.errors.reason" />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="leaveForm.processing"
                        >Save request</Button
                    >
                </form>
            </template>
            <template v-else-if="panel === 'mark' && current">
                <SheetHeader
                    ><SheetTitle
                        >Mark attendance · {{ current.name }}</SheetTitle
                    ><SheetDescription
                        >Correct a missed check-in. Saved in the audit
                        log.</SheetDescription
                    ></SheetHeader
                >
                <form class="flex flex-col gap-3 px-4" @submit.prevent="mark">
                    <Input
                        v-model="markForm.work_date"
                        type="date"
                        :max="today"
                    />
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="s in ['present', 'late', 'leave', 'absent']"
                            :key="s"
                            type="button"
                            class="h-11 rounded-lg border text-sm font-semibold capitalize"
                            :class="
                                markForm.status === s
                                    ? 'border-foreground bg-foreground text-background'
                                    : ''
                            "
                            @click="markForm.status = s"
                        >
                            {{ s }}
                        </button>
                    </div>
                    <Input
                        v-model="markForm.note"
                        placeholder="Note (optional)"
                    />
                    <InputError
                        :message="
                            markForm.errors.work_date || markForm.errors.status
                        "
                    />
                    <Button
                        type="submit"
                        class="h-12"
                        :disabled="markForm.processing"
                        >Save</Button
                    >
                </form>
            </template>
        </SheetContent>
    </Sheet>
</template>
