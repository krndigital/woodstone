<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateItemRequest extends PosRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'note' => ['nullable', 'string', 'max:120'],
        ];
    }
}
