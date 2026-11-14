<?php

declare(strict_types=1);

namespace App\Http\Requests\Floor;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageFloor);
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        $zone = $this->route('zone');

        return [
            'name' => ['required', 'string', 'max:40', Rule::unique('zones', 'name')->ignore($zone instanceof Zone ? $zone->id : null)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
