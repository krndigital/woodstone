<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\PinUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'mustChangePassword' => $user->must_change_password,
            'pin' => [
                'canUse' => $user->role->canUsePin() && $user->two_factor_confirmed_at === null,
                'hasPin' => $user->hasPin(),
                'locked' => $user->pinIsLocked(),
            ],
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password' => $request->password,
            'must_change_password' => false,
        ])->save();

        Audit::record('user.password_changed', $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    /**
     * Set or change the user's own quick PIN (needs the current password).
     */
    public function updatePin(PinUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'pin' => (string) $request->string('pin'),
            'pin_set_at' => now(),
            'pin_failed_attempts' => 0,
            'pin_locked_at' => null,
        ])->save();

        Audit::record('user.pin_set', $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quick PIN saved.')]);

        return back();
    }

    /**
     * Remove the user's own quick PIN.
     */
    public function destroyPin(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['pin' => null, 'pin_set_at' => null, 'pin_failed_attempts' => 0, 'pin_locked_at' => null])->save();

        Audit::record('user.pin_removed', $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quick PIN removed.')]);

        return back();
    }
}
