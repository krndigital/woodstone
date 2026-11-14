<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class StockRequest extends FormRequest
{
    public const QTY = '/^-?\d{1,10}(\.\d{1,3})?$/';

    public const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageStock);
    }

    public function actor(): User
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user;
    }
}
