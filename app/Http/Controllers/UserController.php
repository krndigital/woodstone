<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Users\GuardLastSuperAdmin;
use App\Actions\Users\SaveUser;
use App\Enums\Role;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff accounts (step 1). Routes are behind `can:users.manage`; each action also checks the
 * UserPolicy so an Admin can never touch a Super Admin.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->string('q'));
        $role = Role::tryFrom((string) $request->string('role'));
        $status = (string) $request->string('status');

        /** @var User $actor */
        $actor = $request->user();

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%'])
                ->orWhere('username', 'like', '%'.mb_strtolower($search).'%')))
            ->when($role !== null, fn ($q) => $q->where('role', $role->value))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
                'has_pin' => $user->hasPin(),
                'pin_locked' => $user->pinIsLocked(),
                'must_change_password' => $user->must_change_password,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'can_manage' => $actor->can('update', $user),
                'is_me' => $actor->is($user),
            ])
            ->values();

        $assignable = array_values(array_filter(
            Role::cases(),
            fn (Role $r): bool => $actor->role->canManageRole($r),
        ));

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => ['q' => $search, 'role' => $role?->value, 'status' => $status],
            'roles' => Role::options(),
            'assignableRoles' => array_map(fn (Role $r): array => ['value' => $r->value, 'label' => $r->label(), 'can_use_pin' => $r->canUsePin()], $assignable),
        ]);
    }

    public function store(StoreUserRequest $request, SaveUser $save): RedirectResponse
    {
        /** @var array{name: string, username: string, email?: string|null, phone?: string|null, role: string, password: string, pin?: string|null} $data */
        $data = $request->validated();
        $user = $save->handle($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User :name created. They must change the password at first sign-in.', ['name' => $user->name])]);

        return to_route('users.index');
    }

    public function update(UpdateUserRequest $request, User $user, SaveUser $save): RedirectResponse
    {
        /** @var array{name: string, username: string, email?: string|null, phone?: string|null, role: string, pin?: string|null} $data */
        $data = $request->validated();
        $save->handle($data, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('users.index');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages(['status' => __('You cannot turn off your own account.')]);
        }

        if ($user->is_active) {
            GuardLastSuperAdmin::check($user, 'status');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        if (! $user->is_active) {
            $this->endSessions($user);
        }

        Audit::record($user->is_active ? 'user.activated' : 'user.deactivated', $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => $user->is_active ? __(':name can sign in again.', ['name' => $user->name]) : __(':name is turned off and signed out.', ['name' => $user->name])]);

        return back();
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'password' => (string) $request->string('password'),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        if (! $request->user()?->is($user)) {
            $this->endSessions($user);
        }

        Audit::record('user.password_reset', $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Temporary password set for :name. They must change it at next sign-in.', ['name' => $user->name])]);

        return back();
    }

    public function clearPin(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->forceFill(['pin' => null, 'pin_set_at' => null, 'pin_failed_attempts' => 0, 'pin_locked_at' => null])->save();

        Audit::record('user.pin_cleared', $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('PIN removed for :name.', ['name' => $user->name])]);

        return back();
    }

    public function unlockPin(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->forceFill(['pin_failed_attempts' => 0, 'pin_locked_at' => null])->save();

        Audit::record('user.pin_unlocked', $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('PIN unlocked for :name.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Sign the user out everywhere (database sessions) after deactivation or a password reset.
     */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
