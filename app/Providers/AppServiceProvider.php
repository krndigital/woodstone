<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\TerminalContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TerminalContext::class);
    }

    public function boot(): void
    {
        Settings::flush();
        $this->configureDefaults();
        $this->configureGates();
        $this->configureAuthEvents();
        $this->configureRateLimits();

        // /up health check also proves the database answers (step 15 monitoring).
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));
    }

    /**
     * Public QR page limits, each with its own bucket so they never add up (FR-16.4).
     */
    protected function configureRateLimits(): void
    {
        RateLimiter::for('guest', fn (Request $request): Limit => Limit::perMinute(60)->by('guest|'.$request->ip()));
        RateLimiter::for('guest-call', fn (Request $request): Limit => Limit::perMinute(6)->by('guest-call|'.$request->ip()));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Staff passwords: long enough and not found in known data breaches.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8),
        );
    }

    /**
     * Each Permission is a Gate, so routes use `can:pos.use` style middleware.
     */
    protected function configureGates(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

    protected function configureAuthEvents(): void
    {
        Event::listen(function (Login $event): void {
            $user = $event->user;

            if (! $user instanceof User) {
                return;
            }

            $method = request()->attributes->get('login_method', 'password');
            $changes = ['last_login_at' => now(), 'last_login_ip' => request()->ip()];

            // A successful password sign-in unlocks a locked PIN (D-06).
            if ($method === 'password') {
                $changes['pin_failed_attempts'] = 0;
                $changes['pin_locked_at'] = null;
            }

            $user->forceFill($changes)->saveQuietly();

            Audit::record('auth.login', $user, ['method' => $method], $user);
        });

        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                Audit::record('auth.logout', $event->user, [], $event->user);
            }
        });
    }
}
