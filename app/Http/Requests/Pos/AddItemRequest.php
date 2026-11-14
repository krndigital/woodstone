<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\ValidationRule;

class AddItemRequest extends PosRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'modifier_ids' => ['nullable', 'array', 'max:10'],
            'modifier_ids.*' => ['integer', 'distinct'],
            'note' => ['nullable', 'string', 'max:120'],
        ];
    }
}
