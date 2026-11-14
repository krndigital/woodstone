<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\ValidationRule;

class MergeOrderRequest extends PosRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return ['source_order_id' => ['required', 'integer', 'exists:orders,id']];
    }
}
