<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Responses\LogoutResponse;
use App\Models\User;
use App\Support\Audit;
use App\Support\TerminalContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * A valid bcrypt hash used to keep sign-in timing equal when the username does not exist,
     * so response time does not reveal which usernames are real.
     */
    private const DUMMY_HASH = '$2y$12$9Gjy1UtF6LsDxHbsUAqJ8e6REnSXt9YOXSzi5ynEsS23hvPJcSp7S';

    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Username + password sign-in (D-06). Turned-off accounts cannot sign in.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $username = Str::lower(trim((string) $request->input('username')));
            $password = (string) $request->input('password');

            $user = $username === '' ? null : User::query()->where('username', $username)->first();

            if ($user === null) {
                Hash::check($password, self::DUMMY_HASH);
                Audit::record('auth.failed', null, ['username' => Str::limit($username, 50, '')]);

                return null;
            }

            if (! Hash::check($password, $user->password)) {
                Audit::record('auth.failed', $user, ['username' => $username]);

                return null;
            }

            if (! $user->is_active) {
                Audit::record('auth.blocked_inactive', $user, [], $user);

                throw ValidationException::withMessages([
                    'username' => __('This account is turned off. Please ask your manager.'),
                ]);
            }

            return $user;
        });
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
            'hasTerminal' => app(TerminalContext::class)->current($request) !== null,
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('username')).'|'.$request->ip());

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(30)->by('login-ip|'.$request->ip()),
            ];
        });

        // Quick PIN: 5 tries a minute per person per device, 20 a minute per device.
        RateLimiter::for('pin', function (Request $request) {
            return [
                Limit::perMinute(5)->by('pin|'.$request->input('user_id').'|'.$request->ip()),
                Limit::perMinute(20)->by('pin-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
