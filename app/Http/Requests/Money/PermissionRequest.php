<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request that checks one permission and gives a typed actor.
 */
abstract class PermissionRequest extends FormRequest
{
    public const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

    abstract protected function permission(): Permission;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission($this->permission());
    }

    public function actor(): User
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user;
    }

    public function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
