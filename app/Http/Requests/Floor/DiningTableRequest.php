<?php

declare(strict_types=1);

namespace App\Http\Requests\Floor;

use App\Enums\Permission;
use App\Enums\TableKind;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageFloor);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        $table = $this->route('table');

        return [
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/', Rule::unique('dining_tables', 'code')->ignore($table instanceof DiningTable ? $table->id : null)],
            'kind' => ['required', Rule::enum(TableKind::class)],
            'seats' => ['required', 'integer', 'min:1', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
