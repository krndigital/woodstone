<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;

class RecipeRequest extends StockRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array', 'max:30'],
            'lines.*.stock_item_id' => ['required', 'integer', 'distinct', 'exists:stock_items,id'],
            'lines.*.quantity' => ['required', 'regex:/^\d{1,8}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
        ];
    }
}
