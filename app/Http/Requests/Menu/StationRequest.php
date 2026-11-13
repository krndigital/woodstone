<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use App\Enums\Permission;
use App\Enums\StationKind;
use App\Models\Station;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageMenu);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        $station = $this->route('station');

        return [
            'name' => ['required', 'string', 'max:40', Rule::unique('stations', 'name')->ignore($station instanceof Station ? $station->id : null)],
            'kind' => ['required', Rule::enum(StationKind::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
