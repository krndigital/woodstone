<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string|null $email
 * @property CarbonInterface|null $email_verified_at
 * @property string|null $phone
 * @property Role $role
 * @property bool $is_active
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $pin
 * @property CarbonInterface|null $pin_set_at
 * @property int $pin_failed_attempts
 * @property CarbonInterface|null $pin_locked_at
 * @property CarbonInterface|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonInterface|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'phone', 'password'])]
#[Hidden(['password', 'pin', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Role, active flag and PIN are never mass assignable: they are set explicitly by
     * the user-management actions after an authorization check.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'pin' => 'hashed',
            'pin_set_at' => 'datetime',
            'pin_failed_attempts' => 'integer',
            'pin_locked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->is_active && $this->role->allows($permission);
    }

    public function hasPin(): bool
    {
        return $this->pin !== null;
    }

    public function pinIsLocked(): bool
    {
        return $this->pin_locked_at !== null;
    }

    /**
     * Users who can appear on a terminal's quick-PIN screen.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopePinEligible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('pin')
            ->whereNull('pin_locked_at')
            ->where('role', '!=', Role::SuperAdmin->value);
    }

    /**
     * @return list<string>
     */
    public function permissionValues(): array
    {
        if (! $this->is_active) {
            return [];
        }

        return array_map(fn (Permission $p): string => $p->value, $this->role->permissions());
    }
}
