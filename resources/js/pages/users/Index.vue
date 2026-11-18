<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    KeyRound,
    LockOpen,
    Pencil,
    Plus,
    Power,
    Search,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { index as usersIndex } from '@/routes/users';

type Row = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    phone: string | null;
    role: string;
    role_label: string;
    is_active: boolean;
    has_pin: boolean;
    pin_locked: boolean;
    must_change_password: boolean;
    last_login_at: string | null;
    can_manage: boolean;
    is_me: boolean;
};

type Option = { value: string; label: string };
type AssignableRole = Option & { can_use_pin: boolean; permissions: string[] };

const props = defineProps<{
    users: Row[];
    filters: { q: string; role: string | null; status: string };
    roles: Option[];
    assignableRoles: AssignableRole[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: usersIndex() }],
    },
});

/* ---------- filters (server side) ---------- */
const q = ref(props.filters.q);
const role = ref(props.filters.role ?? '');
const status = ref(props.filters.status);
let timer: ReturnType<typeof setTimeout> | undefined;

function applyFilters(): void {
    router.get(
        usersIndex.url(),
        {
            q: q.value || undefined,
            role: role.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch([role, status], applyFilters);
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

/* ---------- live hints while typing a new login ---------- */
const usernameChecks = computed(() => {
    const u = form.username.trim();
    return [
        { ok: u.length >= 3 && u.length <= 50, text: '3 to 50 characters' },
        {
            ok: u !== '' && /^[a-zA-Z0-9._-]+$/.test(u),
            text: 'Only letters, numbers, dot ( . ), dash ( - ) or underscore ( _ ) — no spaces',
        },
    ];
});
const usernameSuggestions = computed(() => {
    const first =
        form.name
            .trim()
            .toLowerCase()
            .split(/\s+/)[0]
            ?.replace(/[^a-z0-9]/g, '') ?? '';
    if (first === '') return [];
    const role = (form.role || 'staff').replace('_', '');
    return [
        `${first}.${role}`,
        `${first}${new Date().getFullYear() % 100}`,
        `${first}.ws`,
    ].filter((s) => s !== form.username);
});
const passwordChecks = computed(() => {
    const p = form.password;
    return [
        { ok: p.length >= 10, text: 'At least 10 characters' },
        { ok: /[a-zA-Z]/.test(p), text: 'Has a letter' },
        { ok: /\d/.test(p), text: 'Has a number' },
    ];
});
const selectedRole = computed(() =>
    props.assignableRoles.find((r) => r.value === form.role),
);

/* ---------- create / edit drawer ---------- */
const open = ref(false);
const editing = ref<Row | null>(null);
const form = useForm({
    name: '',
    username: '',
    email: '',
    phone: '',
    role:
        props.assignableRoles.find((r) => r.value === 'cashier')?.value ??
        props.assignableRoles[0]?.value ??
        '',
    password: '',
    pin: '',
});

const roleCanUsePin = computed(
    () =>
        props.assignableRoles.find((r) => r.value === form.role)?.can_use_pin ??
        false,
);

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(user: Row): void {
    editing.value = user;
    form.clearErrors();
    form.name = user.name;
    form.username = user.username;
    form.email = user.email ?? '';
    form.phone = user.phone ?? '';
    form.role = user.role;
    form.password = '';
    form.pin = '';
    open.value = true;
}

function generatePassword(): void {
    const chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    const bytes = new Uint32Array(12);
    crypto.getRandomValues(bytes);
    let pw = '';
    do {
        crypto.getRandomValues(bytes);
        pw = Array.from(bytes, (b) => chars[b % chars.length]).join('');
    } while (!/\d/.test(pw) || !/[a-z]/.test(pw));
    form.password = pw;
}

function save(): void {
    if (!roleCanUsePin.value) {
        form.pin = '';
    }

    const target = editing.value
        ? UserController.update(editing.value.id)
        : UserController.store();

    form.transform((data) =>
        editing.value ? { ...data, password: undefined } : data,
    ).submit(target, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}

/* ---------- row actions ---------- */
function toggleStatus(user: Row): void {
    const verb = user.is_active ? 'turn off' : 'turn on';
    if (!window.confirm(`Do you want to ${verb} ${user.name}?`)) {
        return;
    }
    router.visit(UserController.toggleStatus(user.id), {
        preserveScroll: true,
    });
}

const resetFor = ref<Row | null>(null);
const resetForm = useForm({ password: '' });

function openReset(user: Row): void {
    resetFor.value = user;
    resetForm.reset();
    resetForm.clearErrors();
}

function submitReset(): void {
    if (resetFor.value === null) {
        return;
    }
    resetForm.submit(UserController.resetPassword(resetFor.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetFor.value = null;
        },
    });
}

function clearPin(user: Row): void {
    if (window.confirm(`Remove the quick PIN for ${user.name}?`)) {
        router.visit(UserController.clearPin(user.id), {
            preserveScroll: true,
        });
    }
}

function unlockPin(user: Row): void {
    router.visit(UserController.unlockPin(user.id), { preserveScroll: true });
}

function lastSeen(iso: string | null): string {
    if (iso === null) {
        return 'Never';
    }
    return new Date(iso).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <Heading
                title="Users & logins"
                description="Everyone signs in with a username and password. Staff may add a 4-digit PIN for quick switching on registered devices."
            />
            <Button class="ml-auto h-11" @click="openCreate">
                <Plus class="size-4" /> Add user
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full max-w-xs">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    type="search"
                    placeholder="Search name or username"
                    aria-label="Search users"
                    class="h-11 pl-9"
                />
            </div>
            <select
                v-model="role"
                aria-label="Filter by role"
                class="h-11 rounded-md border bg-background px-3 text-sm"
            >
                <option value="">All roles</option>
                <option v-for="r in roles" :key="r.value" :value="r.value">
                    {{ r.label }}
                </option>
            </select>
            <select
                v-model="status"
                aria-label="Filter by status"
                class="h-11 rounded-md border bg-background px-3 text-sm"
            >
                <option value="">Active and turned off</option>
                <option value="active">Active only</option>
                <option value="inactive">Turned off only</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Quick PIN</th>
                        <th class="px-4 py-3 font-medium">Last sign-in</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="border-t"
                        :class="{ 'opacity-60': !user.is_active }"
                    >
                        <td class="px-4 py-3">
                            <div class="font-semibold">
                                {{ user.name }}
                                <span
                                    v-if="user.is_me"
                                    class="text-xs font-normal text-muted-foreground"
                                    >(you)</span
                                >
                            </div>
                            <div class="text-xs text-muted-foreground">
                                @{{ user.username }}
                                <span
                                    v-if="user.must_change_password"
                                    class="ml-1 text-amber-700"
                                    >· must change password</span
                                >
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <Badge variant="secondary">{{
                                user.role_label
                            }}</Badge>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                v-if="user.pin_locked"
                                class="font-semibold text-red-700"
                                >Locked</span
                            >
                            <span v-else-if="user.has_pin">Set</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ lastSeen(user.last_login_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    user.is_active ? 'default' : 'outline'
                                "
                            >
                                {{ user.is_active ? 'Active' : 'Turned off' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="user.can_manage"
                                class="flex justify-end gap-1"
                            >
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    :aria-label="`Edit ${user.name}`"
                                    @click="openEdit(user)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    :aria-label="`Reset password for ${user.name}`"
                                    @click="openReset(user)"
                                >
                                    <KeyRound class="size-4" />
                                </Button>
                                <Button
                                    v-if="user.pin_locked"
                                    size="sm"
                                    variant="ghost"
                                    :aria-label="`Unlock PIN for ${user.name}`"
                                    @click="unlockPin(user)"
                                >
                                    <LockOpen class="size-4" />
                                </Button>
                                <Button
                                    v-if="user.has_pin"
                                    size="sm"
                                    variant="ghost"
                                    :aria-label="`Remove PIN for ${user.name}`"
                                    @click="clearPin(user)"
                                >
                                    <X class="size-4" />
                                </Button>
                                <Button
                                    v-if="!user.is_me"
                                    size="sm"
                                    variant="ghost"
                                    :aria-label="`${user.is_active ? 'Turn off' : 'Turn on'} ${user.name}`"
                                    @click="toggleStatus(user)"
                                >
                                    <Power
                                        class="size-4"
                                        :class="
                                            user.is_active
                                                ? 'text-red-600'
                                                : 'text-green-600'
                                        "
                                    />
                                </Button>
                            </div>
                            <div
                                v-else
                                class="text-right text-xs text-muted-foreground"
                            >
                                Super Admin only
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No users match these filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create / edit drawer -->
    <Sheet v-model:open="open">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>{{
                    editing ? `Edit ${editing.name}` : 'Add user'
                }}</SheetTitle>
                <SheetDescription>
                    {{
                        editing
                            ? 'Change details or role. Use the key button in the list to reset the password.'
                            : 'They must change this password at their first sign-in.'
                    }}
                </SheetDescription>
            </SheetHeader>

            <form class="flex flex-col gap-4 px-4 pb-6" @submit.prevent="save">
                <div class="grid gap-2">
                    <Label for="u-name">Full name</Label>
                    <Input
                        id="u-name"
                        v-model="form.name"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-username">Username</Label>
                    <Input
                        id="u-username"
                        v-model="form.username"
                        required
                        autocapitalize="none"
                        autocomplete="off"
                        placeholder="e.g. sita.waiter"
                    />
                    <ul class="flex flex-col gap-0.5 text-xs">
                        <li
                            v-for="c in usernameChecks"
                            :key="c.text"
                            :class="
                                c.ok
                                    ? 'text-green-700 dark:text-green-400'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ c.ok ? '✓' : '•' }} {{ c.text }}
                        </li>
                        <li class="text-muted-foreground">
                            • Capital letters become small (Sita → sita)
                        </li>
                    </ul>
                    <div
                        v-if="usernameSuggestions.length && !editing"
                        class="flex flex-wrap items-center gap-1 text-xs"
                    >
                        <span class="text-muted-foreground">Try:</span>
                        <button
                            v-for="sug in usernameSuggestions"
                            :key="sug"
                            type="button"
                            class="rounded-full border px-2 py-0.5 font-semibold hover:bg-muted"
                            @click="form.username = sug"
                        >
                            {{ sug }}
                        </button>
                    </div>
                    <InputError :message="form.errors.username" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="u-phone">Phone (optional)</Label>
                        <Input
                            id="u-phone"
                            v-model="form.phone"
                            inputmode="tel"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="u-email">Email (optional)</Label>
                        <Input id="u-email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>Role</Label>
                    <div
                        class="flex flex-wrap gap-2"
                        role="radiogroup"
                        aria-label="Role"
                    >
                        <button
                            v-for="r in assignableRoles"
                            :key="r.value"
                            type="button"
                            role="radio"
                            :aria-checked="form.role === r.value"
                            class="h-10 rounded-lg border px-3 text-sm font-semibold"
                            :class="
                                form.role === r.value
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'hover:bg-muted'
                            "
                            @click="form.role = r.value"
                        >
                            {{ r.label }}
                        </button>
                    </div>
                    <div
                        v-if="selectedRole"
                        class="rounded-lg bg-muted p-2 text-xs"
                    >
                        <b>{{ selectedRole.label }} can:</b>
                        <ul class="mt-1 list-inside list-disc">
                            <li v-for="p in selectedRole.permissions" :key="p">
                                {{ p }}
                            </li>
                        </ul>
                        <p class="mt-1 text-muted-foreground">
                            Change this in Settings → Roles &amp; access.
                        </p>
                    </div>
                    <InputError :message="form.errors.role" />
                </div>
                <div v-if="!editing" class="grid gap-2">
                    <Label for="u-password">Temporary password</Label>
                    <div class="flex gap-2">
                        <Input
                            id="u-password"
                            v-model="form.password"
                            type="text"
                            required
                            autocomplete="new-password"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="generatePassword"
                            >Generate</Button
                        >
                    </div>
                    <ul class="flex flex-col gap-0.5 text-xs">
                        <li
                            v-for="c in passwordChecks"
                            :key="c.text"
                            :class="
                                c.ok
                                    ? 'text-green-700 dark:text-green-400'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ c.ok ? '✓' : '•' }} {{ c.text }}
                        </li>
                    </ul>
                    <p class="text-xs text-muted-foreground">
                        Share it privately. It works once — they choose their
                        own at first sign-in. Or tap Generate.
                    </p>
                    <InputError :message="form.errors.password" />
                </div>
                <div v-if="roleCanUsePin" class="grid gap-2">
                    <Label for="u-pin">{{
                        editing
                            ? 'New quick PIN (leave empty to keep)'
                            : 'Quick PIN (optional)'
                    }}</Label>
                    <Input
                        id="u-pin"
                        v-model="form.pin"
                        inputmode="numeric"
                        maxlength="4"
                        autocomplete="off"
                        class="w-32 tracking-[0.4em]"
                    />
                    <InputError :message="form.errors.pin" />
                </div>
                <p
                    v-else
                    class="rounded-lg bg-muted p-3 text-xs text-muted-foreground"
                >
                    Super Admins always sign in with a password — no PIN.
                </p>
                <Button type="submit" class="h-11" :disabled="form.processing">
                    {{ editing ? 'Save changes' : 'Create user' }}
                </Button>
            </form>
        </SheetContent>
    </Sheet>

    <!-- Reset password drawer -->
    <Sheet
        :open="resetFor !== null"
        @update:open="
            (v: boolean) => {
                if (!v) resetFor = null;
            }
        "
    >
        <SheetContent class="w-full sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Reset password</SheetTitle>
                <SheetDescription>
                    Set a temporary password for {{ resetFor?.name }}. They are
                    signed out and must choose a new one.
                </SheetDescription>
            </SheetHeader>
            <form
                class="flex flex-col gap-4 px-4 pb-6"
                @submit.prevent="submitReset"
            >
                <div class="grid gap-2">
                    <Label for="r-password">Temporary password</Label>
                    <Input
                        id="r-password"
                        v-model="resetForm.password"
                        type="text"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="resetForm.errors.password" />
                </div>
                <Button
                    type="submit"
                    class="h-11"
                    :disabled="resetForm.processing"
                    >Set temporary password</Button
                >
            </form>
        </SheetContent>
    </Sheet>
</template>
