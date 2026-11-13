<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Delete } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import { login } from '@/routes';
import { store } from '@/routes/pin';

type PinUser = { id: number; name: string; role: string };

const props = defineProps<{
    terminal: string;
    users: PinUser[];
}>();

defineOptions({
    layout: {
        title: 'Switch user',
        description: 'Pick your name and enter your 4-digit PIN',
    },
});

const { getInitials } = useInitials();
const selected = ref<number | null>(props.users[0]?.id ?? null);
const form = useForm({ user_id: selected.value, pin: '' });
const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'C', '0', '⌫'];

const selectedName = computed(
    () => props.users.find((u) => u.id === selected.value)?.name ?? '',
);

function pick(id: number): void {
    selected.value = id;
    form.clearErrors();
    form.pin = '';
}

function submit(): void {
    if (selected.value === null || form.pin.length !== 4) {
        return;
    }

    form.user_id = selected.value;
    form.submit(store(), {
        onFinish: () => form.reset('pin'),
    });
}

function press(key: string): void {
    if (form.processing) {
        return;
    }

    if (key === 'C') {
        form.pin = '';
    } else if (key === '⌫') {
        form.pin = form.pin.slice(0, -1);
    } else if (form.pin.length < 4) {
        form.pin += key;
    }
}

watch(
    () => form.pin,
    (value) => {
        if (value.length === 4) {
            submit();
        }
    },
);

function onKeydown(event: KeyboardEvent): void {
    if (/^\d$/.test(event.key)) {
        press(event.key);
    } else if (event.key === 'Backspace') {
        press('⌫');
    }
}
</script>

<template>
    <Head title="Switch user" />

    <div
        class="flex flex-col gap-5 outline-none"
        tabindex="0"
        @keydown="onKeydown"
    >
        <p class="text-center text-xs text-muted-foreground">
            Device: <strong>{{ terminal }}</strong>
        </p>

        <div
            v-if="users.length === 0"
            class="rounded-lg border p-4 text-center text-sm text-muted-foreground"
        >
            Nobody has a quick PIN yet. Sign in with your password and set one
            under Settings → Security.
        </div>

        <div
            v-else
            class="grid grid-cols-3 gap-2"
            role="radiogroup"
            aria-label="Who is signing in?"
        >
            <button
                v-for="user in users"
                :key="user.id"
                type="button"
                role="radio"
                :aria-checked="selected === user.id"
                class="flex min-h-24 flex-col items-center justify-center gap-1 rounded-xl border p-2 text-sm font-semibold transition"
                :class="
                    selected === user.id
                        ? 'border-2 border-primary bg-primary/10'
                        : 'hover:bg-muted'
                "
                @click="pick(user.id)"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-full bg-foreground text-sm text-background"
                    >{{ getInitials(user.name) }}</span
                >
                <span class="line-clamp-1">{{ user.name }}</span>
                <span class="text-xs font-normal text-muted-foreground">{{
                    user.role
                }}</span>
            </button>
        </div>

        <template v-if="users.length > 0">
            <div
                class="flex justify-center gap-4 py-1"
                :aria-label="`PIN for ${selectedName}`"
            >
                <span
                    v-for="i in 4"
                    :key="i"
                    class="size-4 rounded-full border-2 border-primary"
                    :class="{ 'bg-primary': form.pin.length >= i }"
                />
            </div>
            <InputError class="text-center" :message="form.errors.pin" />

            <div class="grid grid-cols-3 gap-2">
                <Button
                    v-for="key in keys"
                    :key="key"
                    type="button"
                    variant="outline"
                    class="h-16 text-2xl font-semibold"
                    :disabled="form.processing"
                    :aria-label="
                        key === '⌫' ? 'Delete' : key === 'C' ? 'Clear' : key
                    "
                    @click="press(key)"
                >
                    <Delete v-if="key === '⌫'" class="size-6" />
                    <template v-else>{{ key }}</template>
                </Button>
            </div>
        </template>

        <Link
            :href="login()"
            class="text-center text-sm font-semibold text-primary hover:underline"
        >
            Sign in with password instead
        </Link>
    </div>
</template>
