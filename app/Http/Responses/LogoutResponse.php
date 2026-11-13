<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\TerminalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * After sign-out, a registered terminal goes straight to the quick-PIN screen
 * so the next person can switch in; other devices go to the sign-in page.
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $onTerminal = app(TerminalContext::class)->current($request) !== null;

        return redirect()->route($onTerminal ? 'pin.create' : 'login');
    }
}
