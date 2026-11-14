<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Enums\OrderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class OpenOrderRequest extends PosRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(OrderType::class)],
            'dining_table_id' => ['nullable', 'required_if:type,dine_in', 'integer', 'exists:dining_tables,id'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:60'],
            'customer_name' => ['nullable', 'string', 'max:80'],
            'customer_phone' => ['nullable', 'required_if:type,delivery', 'string', 'regex:/^[0-9+\- ]{7,20}$/'],
            'delivery_address' => ['nullable', 'required_if:type,delivery', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
