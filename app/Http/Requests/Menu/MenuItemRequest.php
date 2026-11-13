<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use App\Enums\Permission;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuItemRequest extends FormRequest
{
    /** Up to Rs 99,99,999.99 with at most 2 decimals. */
    public const MONEY = '/^\d{1,7}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageMenu);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $itemId = $item instanceof MenuItem ? $item->id : null;

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('menu_items', 'code')->ignore($itemId)],
            'category_id' => ['required', 'integer', Rule::exists('menu_categories', 'id')->where('is_active', true)],
            'station_id' => ['required', 'integer', Rule::exists('stations', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'regex:'.self::MONEY],
            'cost' => ['nullable', 'regex:'.self::MONEY],
            'is_veg' => ['boolean'],
            'vat_applicable' => ['boolean'],
            'show_on_qr' => ['boolean'],
            'available_from' => ['nullable', 'date_format:H:i', 'required_with:available_to'],
            'available_to' => ['nullable', 'date_format:H:i', 'required_with:available_from', 'different:available_from'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'modifiers' => ['nullable', 'array', 'max:20'],
            'modifiers.*.name' => ['required', 'string', 'max:60'],
            'modifiers.*.price' => ['nullable', 'regex:'.self::MONEY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.regex' => 'Enter a price like 220 or 220.50.',
            'cost.regex' => 'Enter a cost like 85 or 85.50.',
            'modifiers.*.price.regex' => 'Enter an extra price like 50 or 50.50.',
            'code.regex' => 'Use letters, numbers and dashes only, e.g. MOMO-01.',
        ];
    }
}
