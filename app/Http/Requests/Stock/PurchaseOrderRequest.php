<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;

class PurchaseOrderRequest extends StockRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'expected_on' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:200'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.stock_item_id' => ['required', 'integer', 'distinct', 'exists:stock_items,id'],
            'lines.*.quantity' => ['required', 'regex:/^\d{1,10}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'lines.*.expected_rate' => ['required', 'regex:'.self::MONEY],
        ];
    }
}
