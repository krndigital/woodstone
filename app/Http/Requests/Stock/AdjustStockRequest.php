<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Enums\StockMovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends StockRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_map(fn (StockMovementType $t): string => $t->value, StockMovementType::manual()))],
            'quantity' => ['required', 'regex:'.self::QTY],
            'unit_cost' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}
