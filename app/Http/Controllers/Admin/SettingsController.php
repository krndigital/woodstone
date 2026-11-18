<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandingRequest;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\User;
use App\Support\BsDate;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Restaurant, billing and tax settings (step 4). Super Admin only (`settings.manage`).
 */
class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Settings', [
            'settings' => Settings::all(),
            'bsToday' => BsDate::long(now()),
            'currentFiscalYear' => BsDate::fiscalYear(now()),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Store numbers in a stable form ("13", "12.5", "15000.00"); no float maths.
        foreach (['vat_percent', 'service_charge_percent'] as $key) {
            $value = (string) $data[$key];
            $trimmed = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : ltrim($value, '0');
            $data[$key] = $trimmed === '' ? '0' : $trimmed;
        }
        $data['default_credit_limit'] = Money::fromPaisa(Money::toPaisa((string) $data['default_credit_limit']));
        foreach (['payment_qr', 'payment_card', 'payment_due', 'auto_print', 'guest_call_any_status', 'payment_bank', 'round_off', 'loyalty_enabled'] as $key) {
            $data[$key] = $request->boolean($key);
        }
        $data['cashier_discount_limit_percent'] = (int) $data['cashier_discount_limit_percent'];
        foreach (['loyalty_point_value', 'tier_silver_spend', 'tier_gold_spend'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = Money::fromPaisa(Money::toPaisa((string) $data[$key]));
            }
        }
        foreach (['loyalty_points_per_100', 'loyalty_expiry_days'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (int) $data[$key];
            }
        }

        /** @var User $user */
        $user = $request->user();
        Settings::save($data, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }

    /** Logo (sidebar, sign-in page, bill) or browser favicon. */
    public function uploadBranding(BrandingRequest $request): RedirectResponse
    {
        $kind = $request->string('kind')->toString();
        $key = $kind.'_path';
        $old = Settings::get($key);
        $path = $request->file('file')?->store('branding', 'public');
        if (! is_string($path)) {
            return back()->withErrors(['file' => __('Upload failed. Try again.')]);
        }

        /** @var User $user */
        $user = $request->user();
        Settings::save([$key => $path], $user);
        if (is_string($old) && $old !== '') {
            Storage::disk('public')->delete($old);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $kind === 'logo' ? __('Logo saved.') : __('Favicon saved. Refresh the browser tab to see it.')]);

        return back();
    }

    public function removeBranding(Request $request, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, ['logo', 'favicon'], true), 404);
        $old = Settings::get($kind.'_path');

        /** @var User $user */
        $user = $request->user();
        Settings::save([$kind.'_path' => null], $user);
        if (is_string($old) && $old !== '') {
            Storage::disk('public')->delete($old);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Removed.')]);

        return back();
    }
}
