<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Branding;
use App\Support\TerminalContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->sharedUser($request->user()),
            ],
            'terminal' => $this->sharedTerminal($request),
            'branding' => ['logo' => Branding::url('logo')],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function sharedTerminal(Request $request): ?array
    {
        $terminal = app(TerminalContext::class)->current($request);

        return $terminal === null ? null : ['id' => $terminal->id, 'name' => $terminal->name];
    }

    /**
     * Only the fields the UI needs; never the whole model.
     *
     * @return array<string, mixed>|null
     */
    private function sharedUser(mixed $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'permissions' => $user->permissionValues(),
            'has_pin' => $user->hasPin(),
            'can_use_pin' => $user->role->canUsePin(),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
        ];
    }
}
