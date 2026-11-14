<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PurchaseOrderStatusRequest extends StockRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['sent', 'cancelled'])]];
    }
}
