<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Logo / favicon upload. No SVG: it can carry scripts. */
class BrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageSettings);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:logo,favicon'],
            'file' => ['required', 'file', 'max:1024', 'mimes:png,jpg,jpeg,webp,ico', 'mimetypes:image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon'],
        ];
    }
}
