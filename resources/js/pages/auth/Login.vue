<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { create as pinScreen } from '@/routes/pin';

defineOptions({
    layout: {
        title: 'Sign in',
        description: 'Use the username and password your manager gave you',
    },
});

defineProps<{
    status?: string;
    hasTerminal: boolean;
}>();
</script>

<template>
    <Head title="Sign in" />

    <div
        v-if="status"
        class="mb-4 rounded-lg bg-amber-50 p-3 text-center text-sm font-medium text-amber-800"
        role="status"
    >
        {{ status }}
    </div>

    <PasskeyVerify separator="Or sign in with your username" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        :reset-on-error="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    type="text"
                    name="username"
                    required
                    v-focus
                    autocapitalize="none"
                    autocomplete="username"
                    spellcheck="false"
                    placeholder="e.g. ram.cashier"
                    class="h-12 text-base"
                />
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Your password"
                    class="h-12 text-base"
                />
                <InputError :message="errors.password" />
                <p class="text-xs text-muted-foreground">
                    Forgot your password? Ask your manager to reset it.
                </p>
            </div>

            <Button
                type="submit"
                size="lg"
                class="h-12 w-full text-base"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Sign in
            </Button>
        </div>
    </Form>

    <Link
        v-if="hasTerminal"
        :href="pinScreen()"
        class="block rounded-lg border p-3 text-center text-sm font-semibold hover:bg-muted"
    >
        Switch user with quick PIN
    </Link>
</template>
