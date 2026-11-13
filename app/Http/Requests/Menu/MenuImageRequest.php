<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class MenuImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageMenu);
    }

    /**
     * JPG, PNG or WebP only (no SVG: it can carry scripts), max 2 MB.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'],
        ];
    }
}
