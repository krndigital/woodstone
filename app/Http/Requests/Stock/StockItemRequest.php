<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Enums\StockKind;
use App\Models\StockItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StockItemRequest extends StockRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        $item = $this->route('stockItem');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('stock_items', 'name')->ignore($item instanceof StockItem ? $item->id : null)],
            'kind' => ['required', Rule::enum(StockKind::class)],
            'unit' => ['required', 'string', 'max:12'],
            'purchase_unit' => ['nullable', 'required_with:purchase_factor', 'string', 'max:12'],
            'purchase_factor' => ['nullable', 'required_with:purchase_unit', 'regex:/^\d{1,8}(\.\d{1,3})?$/', 'not_in:0,0.0,0.00,0.000'],
            'reorder_level' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,3})?$/'],
            'is_active' => ['boolean'],
        ];
    }
}
