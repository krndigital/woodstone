<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\TerminalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quick PIN switch on a registered terminal (D-06).
 * - Only works on a device registered by an admin (encrypted cookie + hashed token).
 * - Super Admin and users with two-factor sign-in never use a PIN.
 * - 3 wrong PINs lock the PIN until the user signs in with their password.
 */
class PinLoginController extends Controller
{
    public const MAX_ATTEMPTS = 3;

    public function create(Request $request, TerminalContext $terminals): Response|RedirectResponse
    {
        $terminal = $terminals->current($request);

        if ($terminal === null) {
            return redirect()->route('login')
                ->with('status', __('This device is not registered for quick PIN. Please sign in with your password.'));
        }

        $users = User::query()->pinEligible()
            ->whereNull('two_factor_confirmed_at')
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->label(),
            ])
            ->values();

        return Inertia::render('auth/Pin', [
            'terminal' => $terminal->name,
            'users' => $users,
        ]);
    }

    public function store(PinLoginRequest $request, TerminalContext $terminals): RedirectResponse
    {
        if ($terminals->current($request) === null) {
            throw ValidationException::withMessages(['pin' => __('This device is not registered for quick PIN.')]);
        }

        $user = User::query()->find($request->integer('user_id'));

        if (! $user instanceof User || ! $user->is_active || ! $user->hasPin()
            || $user->role === Role::SuperAdmin || $user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages(['pin' => __('Quick PIN is not available for this person. Please use your password.')]);
        }

        if ($user->pinIsLocked()) {
            throw ValidationException::withMessages(['pin' => __('PIN is locked after 3 wrong tries. Please sign in with your password.')]);
        }

        if (! Hash::check((string) $request->string('pin'), (string) $user->pin)) {
            $attempts = $user->pin_failed_attempts + 1;
            $locked = $attempts >= self::MAX_ATTEMPTS;

            $user->forceFill([
                'pin_failed_attempts' => $attempts,
                'pin_locked_at' => $locked ? now() : null,
            ])->saveQuietly();

            Audit::record($locked ? 'auth.pin_locked' : 'auth.pin_failed', $user, ['attempt' => $attempts], $user);

            throw ValidationException::withMessages(['pin' => $locked
                ? __('PIN is locked after 3 wrong tries. Please sign in with your password.')
                : trans_choice('Wrong PIN. :count try left.|Wrong PIN. :count tries left.', self::MAX_ATTEMPTS - $attempts, ['count' => self::MAX_ATTEMPTS - $attempts]),
            ]);
        }

        $user->forceFill(['pin_failed_attempts' => 0])->saveQuietly();

        $request->attributes->set('login_method', 'pin');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
