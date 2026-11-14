<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PurchaseRequest extends StockRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'purchased_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'supplier_bill_no' => ['nullable', 'string', 'max:40'],
            'paid_amount' => ['nullable', 'regex:'.self::MONEY],
            'paid_method' => ['nullable', Rule::in(['cash', 'qr', 'card', 'bank'])],
            'note' => ['nullable', 'string', 'max:200'],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:purchased_on'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'lines.*.quantity' => ['required', 'regex:/^\d{1,10}(\.\d{1,3})?$/'],
            'lines.*.in_purchase_unit' => ['boolean'],
            'lines.*.rate' => ['required', 'regex:'.self::MONEY],
            'lines.*.po_line_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return list<array{stock_item_id: int, quantity: string, in_purchase_unit: bool, rate: string, po_line_id: int|null}>
     */
    public function lines(): array
    {
        $out = [];
        foreach ((array) $this->validated('lines', []) as $l) {
            $l = (array) $l;
            $out[] = [
                'stock_item_id' => (int) $l['stock_item_id'],
                'quantity' => (string) $l['quantity'],
                'in_purchase_unit' => filter_var($l['in_purchase_unit'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'rate' => (string) $l['rate'],
                'po_line_id' => isset($l['po_line_id']) ? (int) $l['po_line_id'] : null,
            ];
        }

        return $out;
    }
}
