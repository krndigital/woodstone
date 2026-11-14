<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SupplierRequest extends StockRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('suppliers', 'name')->ignore($supplier instanceof Supplier ? $supplier->id : null)],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\- ]{7,20}$/'],
            'pan' => ['nullable', 'regex:/^\d{9}$/'],
            'address' => ['nullable', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ];
    }
}
