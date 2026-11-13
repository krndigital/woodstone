<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a manager sets a temporary password, the user must choose their own before using the app.
 */
class RequirePasswordChange
{
    /** @var list<string> */
    private const ALLOWED_ROUTES = [
        'security.edit',
        'user-password.update',
        'logout',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('security.edit')
                ->with('status', __('Please choose your own password to continue.'));
        }

        return $next($request);
    }
}
