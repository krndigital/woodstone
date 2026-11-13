<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

type PinState = {
    canUse: boolean;
    hasPin: boolean;
    locked: boolean;
};

type Props = {
    passwordRules: string;
    mustChangePassword: boolean;
    pin: PinState;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

function removePin(): void {
    if (window.confirm('Remove your quick PIN?')) {
        router.visit(SecurityController.destroyPin(), { preserveScroll: true });
    }
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <div
        v-if="mustChangePassword"
        class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-900"
        role="alert"
    >
        Your manager set a temporary password. Please choose your own password
        below to continue.
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Update password"
            description="Ensure your account is using a long, random password to stay secure"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Current password</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Current password"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="New password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Save
                </Button>
            </div>
        </Form>
    </div>

    <div v-if="pin.canUse" class="space-y-6">
        <Heading
            variant="small"
            title="Quick PIN"
            :description="
                pin.hasPin
                    ? pin.locked
                        ? 'Your PIN is locked after 3 wrong tries. Set a new one or sign in with your password to unlock it.'
                        : 'Your 4-digit PIN works on registered counter and kitchen devices.'
                    : 'Add a 4-digit PIN to switch in quickly on registered devices.'
            "
        />

        <Form
            v-bind="SecurityController.updatePin.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            :reset-on-error="['current_password', 'pin', 'pin_confirmation']"
            class="space-y-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                    <Label for="pin">New PIN</Label>
                    <Input
                        id="pin"
                        name="pin"
                        type="password"
                        inputmode="numeric"
                        maxlength="4"
                        autocomplete="off"
                        class="tracking-[0.4em]"
                    />
                    <InputError :message="errors.pin" />
                </div>
                <div class="grid gap-2">
                    <Label for="pin_confirmation">Repeat PIN</Label>
                    <Input
                        id="pin_confirmation"
                        name="pin_confirmation"
                        type="password"
                        inputmode="numeric"
                        maxlength="4"
                        autocomplete="off"
                        class="tracking-[0.4em]"
                    />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="pin_current_password">Your password</Label>
                <PasswordInput
                    id="pin_current_password"
                    name="current_password"
                    autocomplete="current-password"
                    placeholder="Current password"
                />
                <InputError :message="errors.current_password" />
            </div>
            <div class="flex items-center gap-3">
                <Button :disabled="processing">Save PIN</Button>
                <Button
                    v-if="pin.hasPin"
                    type="button"
                    variant="outline"
                    @click="removePin"
                >
                    Remove PIN
                </Button>
            </div>
        </Form>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
