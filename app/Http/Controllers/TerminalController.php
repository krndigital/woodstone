<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Terminal;
use App\Models\User;
use App\Support\Audit;
use App\Support\TerminalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared devices allowed to use quick PIN. Routes are behind `can:terminals.manage`.
 */
class TerminalController extends Controller
{
    public function index(Request $request, TerminalContext $context): Response
    {
        $current = $context->current($request);

        $terminals = Terminal::query()->with('registeredBy:id,name')
            ->latest()
            ->get()
            ->map(fn (Terminal $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'registered_by' => $t->registeredBy?->name,
                'created_at' => $t->created_at?->toIso8601String(),
                'last_seen_at' => $t->last_seen_at?->toIso8601String(),
                'last_ip' => $t->last_ip,
                'revoked' => $t->revoked_at !== null,
                'is_this_device' => $current?->id === $t->id,
            ])
            ->values();

        return Inertia::render('terminals/Index', [
            'terminals' => $terminals,
            'thisDeviceRegistered' => $current !== null,
        ]);
    }

    public function store(Request $request, TerminalContext $context): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        if ($context->current($request) !== null) {
            return back()->withErrors(['name' => __('This device is already registered.')]);
        }

        /** @var User $user */
        $user = $request->user();
        $terminal = $context->register($data['name'], $user);

        Audit::record('terminal.registered', $terminal, ['name' => $terminal->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('This device is now ":name". Staff can switch in with their PIN.', ['name' => $terminal->name])]);

        return back();
    }

    public function destroy(Request $request, Terminal $terminal, TerminalContext $context): RedirectResponse
    {
        $isThisDevice = $context->current($request)?->id === $terminal->id;

        $terminal->forceFill(['revoked_at' => now()])->save();

        if ($isThisDevice) {
            $context->forget();
        }

        Audit::record('terminal.revoked', $terminal, ['name' => $terminal->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('":name" can no longer use quick PIN.', ['name' => $terminal->name])]);

        return back();
    }
}
