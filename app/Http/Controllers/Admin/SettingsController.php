<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\User;
use App\Support\BsDate;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
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
        foreach (['payment_qr', 'payment_card', 'payment_due', 'auto_print', 'guest_call_any_status'] as $key) {
            $data[$key] = $request->boolean($key);
        }
        $data['cashier_discount_limit_percent'] = (int) $data['cashier_discount_limit_percent'];

        /** @var User $user */
        $user = $request->user();
        Settings::save($data, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }
}
