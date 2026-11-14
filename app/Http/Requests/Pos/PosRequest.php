<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for POS requests: everyone with `pos.use` (waiters, cashiers, managers).
 */
abstract class PosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::UsePos);
    }

    public function actor(): User
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user;
    }
}
