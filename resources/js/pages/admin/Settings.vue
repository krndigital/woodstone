<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as settingsEdit } from '@/routes/admin/settings';

type AppSettings = {
    restaurant_name: string;
    address: string;
    pan: string | null;
    phone: string | null;
    bill_footer: string | null;
    vat_percent: string;
    service_charge_percent: string;
    cashier_discount_limit_percent: number;
    payment_qr: boolean;
    payment_card: boolean;
    payment_bank: boolean;
    round_off: boolean;
    loyalty_enabled: boolean;
    loyalty_points_per_100: number;
    loyalty_point_value: string;
    loyalty_expiry_days: number;
    tier_silver_spend: string;
    tier_gold_spend: string;
    payment_due: boolean;
    default_credit_limit: string;
    auto_print: boolean;
    guest_call_any_status: boolean;
    fiscal_year_label: string;
    fiscal_year_starts_on: string;
};

const props = defineProps<{
    settings: AppSettings;
    bsToday: string;
    currentFiscalYear: { label: string; starts_on: string };
}>();

function useCurrentFiscalYear(): void {
    form.fiscal_year_label = props.currentFiscalYear.label;
    form.fiscal_year_starts_on = props.currentFiscalYear.starts_on;
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Settings', href: settingsEdit() }] },
});

const form = useForm({
    ...props.settings,
    pan: props.settings.pan ?? '',
    phone: props.settings.phone ?? '',
    bill_footer: props.settings.bill_footer ?? '',
});

// Preview only — the server always recalculates bills.
const preview = computed(() => {
    const base = 100000; // Rs 1,000 in paisa
    const pct = (v: string): number => Math.round(Number(v || 0) * 100);
    const svc = Math.round((base * pct(form.service_charge_percent)) / 10000);
    const vat = Math.round(((base + svc) * pct(form.vat_percent)) / 10000);
    const rs = (p: number): string =>
        (p / 100).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    return { svc: rs(svc), vat: rs(vat), total: rs(base + svc + vat) };
});

function save(): void {
    form.submit(SettingsController.update(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Settings" />

    <form class="flex max-w-5xl flex-col gap-6 p-4" @submit.prevent="save">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Settings</h1>
            <p class="text-sm text-muted-foreground">
                Only the Super Admin can change these. Every change is recorded
                in the audit log.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <section class="flex flex-col gap-4 rounded-xl border p-5">
                <h2 class="font-bold">Billing & tax</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="svc">Service charge %</Label>
                        <Input
                            id="svc"
                            v-model="form.service_charge_percent"
                            inputmode="decimal"
                        />
                        <InputError
                            :message="form.errors.service_charge_percent"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="vat">VAT %</Label>
                        <Input
                            id="vat"
                            v-model="form.vat_percent"
                            inputmode="decimal"
                        />
                        <p class="text-xs text-muted-foreground">
                            Nepal standard rate is 13% if VAT registered.
                        </p>
                        <InputError :message="form.errors.vat_percent" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="limit">Cashier discount limit %</Label>
                        <Input
                            id="limit"
                            v-model.number="form.cashier_discount_limit_percent"
                            type="number"
                            min="0"
                            max="100"
                        />
                        <p class="text-xs text-muted-foreground">
                            Above this needs manager approval.
                        </p>
                        <InputError
                            :message="
                                form.errors.cashier_discount_limit_percent
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="credit">Default due limit (Rs)</Label>
                        <Input
                            id="credit"
                            v-model="form.default_credit_limit"
                            inputmode="decimal"
                        />
                        <InputError
                            :message="form.errors.default_credit_limit"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fy">Fiscal year</Label>
                        <Input
                            id="fy"
                            v-model="form.fiscal_year_label"
                            placeholder="2083/84"
                        />
                        <p class="text-xs text-muted-foreground">
                            Bill numbers restart when this changes (Shrawan 1).
                        </p>
                        <InputError :message="form.errors.fiscal_year_label" />
                        <p class="text-xs text-muted-foreground">
                            Today is {{ bsToday }} BS · current fiscal year
                            {{ currentFiscalYear.label }}
                            <button
                                v-if="
                                    form.fiscal_year_label !==
                                    currentFiscalYear.label
                                "
                                type="button"
                                class="font-semibold text-orange-700 underline"
                                @click="useCurrentFiscalYear"
                            >
                                use it
                            </button>
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="fys">Fiscal year started on</Label>
                        <Input
                            id="fys"
                            v-model="form.fiscal_year_starts_on"
                            type="date"
                        />
                        <InputError
                            :message="form.errors.fiscal_year_starts_on"
                        />
                    </div>
                </div>

                <h2 class="mt-2 font-bold">Payment methods</h2>
                <div class="flex flex-wrap gap-3 text-sm">
                    <span
                        class="rounded-lg border bg-muted px-3 py-2 font-semibold"
                        >Cash (always on)</span
                    >
                    <label
                        class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        ><input
                            v-model="form.payment_qr"
                            type="checkbox"
                            class="size-4"
                        />
                        QR (Fonepay / eSewa, checked by staff)</label
                    >
                    <label
                        class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        ><input
                            v-model="form.payment_card"
                            type="checkbox"
                            class="size-4"
                        />
                        Card</label
                    >
                    <label
                        class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        ><input
                            v-model="form.payment_bank"
                            type="checkbox"
                            class="size-4"
                        />
                        Bank transfer</label
                    >
                    <label
                        class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        ><input
                            v-model="form.round_off"
                            type="checkbox"
                            class="size-4"
                        />
                        Round total to the nearest rupee</label
                    >
                    <label
                        class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        ><input
                            v-model="form.payment_due"
                            type="checkbox"
                            class="size-4"
                        />
                        Put on due</label
                    >
                    <label
                        class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        ><input
                            v-model="form.auto_print"
                            type="checkbox"
                            class="size-4"
                        />
                        Print bill after payment</label
                    >
                    <label
                        class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        ><input
                            v-model="form.guest_call_any_status"
                            type="checkbox"
                            class="size-4"
                        />
                        Guests can call from free tables (QR)</label
                    >
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-xl border p-5 text-sm">
                <h2 class="font-bold">Preview: Rs 1,000 order</h2>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Subtotal</span
                    ><span class="tabular-nums">Rs 1,000.00</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground"
                        >Service charge ({{
                            form.service_charge_percent || 0
                        }}%)</span
                    ><span class="tabular-nums">Rs {{ preview.svc }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground"
                        >VAT ({{ form.vat_percent || 0 }}%)</span
                    ><span class="tabular-nums">Rs {{ preview.vat }}</span>
                </div>
                <div
                    class="flex justify-between border-t pt-2 text-base font-bold"
                >
                    <span>Total</span
                    ><span class="tabular-nums">Rs {{ preview.total }}</span>
                </div>
            </section>
        </div>

        <section class="grid gap-4 rounded-xl border p-5 sm:grid-cols-3">
            <h2 class="font-bold sm:col-span-3">Loyalty points and tiers</h2>
            <label class="flex items-center gap-2 text-sm sm:col-span-3"
                ><input
                    v-model="form.loyalty_enabled"
                    type="checkbox"
                    class="size-4"
                />
                Give loyalty points</label
            >
            <div class="grid gap-2">
                <Label for="lp">Points per Rs 100 paid</Label
                ><Input
                    id="lp"
                    v-model.number="form.loyalty_points_per_100"
                    type="number"
                    min="0"
                    max="100"
                /><InputError :message="form.errors.loyalty_points_per_100" />
            </div>
            <div class="grid gap-2">
                <Label for="lv">1 point is worth (Rs)</Label
                ><Input
                    id="lv"
                    v-model="form.loyalty_point_value"
                    inputmode="decimal"
                /><InputError :message="form.errors.loyalty_point_value" />
            </div>
            <div class="grid gap-2">
                <Label for="le">Points expire after (days)</Label
                ><Input
                    id="le"
                    v-model.number="form.loyalty_expiry_days"
                    type="number"
                    min="30"
                /><InputError :message="form.errors.loyalty_expiry_days" />
            </div>
            <div class="grid gap-2">
                <Label for="ts">Silver from (Rs spent in 12 months)</Label
                ><Input
                    id="ts"
                    v-model="form.tier_silver_spend"
                    inputmode="decimal"
                /><InputError :message="form.errors.tier_silver_spend" />
            </div>
            <div class="grid gap-2">
                <Label for="tg">Gold from (Rs spent in 12 months)</Label
                ><Input
                    id="tg"
                    v-model="form.tier_gold_spend"
                    inputmode="decimal"
                /><InputError :message="form.errors.tier_gold_spend" />
            </div>
        </section>

        <section class="grid gap-4 rounded-xl border p-5 sm:grid-cols-2">
            <h2 class="font-bold sm:col-span-2">
                Restaurant (printed on bills)
            </h2>
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.restaurant_name" />
                <InputError :message="form.errors.restaurant_name" />
            </div>
            <div class="grid gap-2">
                <Label for="address">Address</Label>
                <Input id="address" v-model="form.address" />
                <InputError :message="form.errors.address" />
            </div>
            <div class="grid gap-2">
                <Label for="pan">PAN (9 digits)</Label>
                <Input id="pan" v-model="form.pan" inputmode="numeric" />
                <InputError :message="form.errors.pan" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Phone</Label>
                <Input id="phone" v-model="form.phone" />
                <InputError :message="form.errors.phone" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label for="footer">Bill footer</Label>
                <Input id="footer" v-model="form.bill_footer" />
                <InputError :message="form.errors.bill_footer" />
            </div>
        </section>

        <div>
            <Button type="submit" class="h-11 px-6" :disabled="form.processing"
                >Save settings</Button
            >
        </div>
    </form>
</template>
