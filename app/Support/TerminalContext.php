<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Terminal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Resolves the registered terminal (shared device) for the current request from its cookie.
 */
final class TerminalContext
{
    private const COOKIE_MINUTES = 60 * 24 * 365 * 3;

    private ?Terminal $resolved = null;

    /** Object id of the request the cached value belongs to (tests reuse one app for many requests). */
    private ?int $requestId = null;

    public function current(Request $request): ?Terminal
    {
        if ($this->requestId === spl_object_id($request)) {
            return $this->resolved;
        }

        $this->requestId = spl_object_id($request);
        $this->resolved = null;
        $token = $request->cookie(Terminal::COOKIE);

        if (! is_string($token) || strlen($token) < 40) {
            return null;
        }

        $terminal = Terminal::query()->active()
            ->where('token_hash', Terminal::hashToken($token))
            ->first();

        if ($terminal !== null && ($terminal->last_seen_at === null || $terminal->last_seen_at->lt(now()->subMinutes(5)))) {
            $terminal->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        }

        return $this->resolved = $terminal;
    }

    /**
     * Register the device making this request and set its cookie. Returns the new terminal.
     */
    public function register(string $name, User $by): Terminal
    {
        $token = Str::random(64);

        $terminal = new Terminal(['name' => $name]);
        $terminal->forceFill([
            'token_hash' => Terminal::hashToken($token),
            'registered_by' => $by->id,
            'last_seen_at' => now(),
        ])->save();

        Cookie::queue(Cookie::make(
            Terminal::COOKIE,
            $token,
            self::COOKIE_MINUTES,
            '/',
            null,
            null,
            true,
            false,
            'lax',
        ));

        $this->resolved = $terminal;
        $this->requestId = spl_object_id(request());

        return $terminal;
    }

    public function forget(): void
    {
        Cookie::queue(Cookie::forget(Terminal::COOKIE));
        $this->resolved = null;
        $this->requestId = spl_object_id(request());
    }
}
