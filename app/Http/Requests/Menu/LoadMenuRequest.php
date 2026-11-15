<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class LoadMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission(Permission::ManageMenu);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['archive_others' => ['boolean']];
    }
}
