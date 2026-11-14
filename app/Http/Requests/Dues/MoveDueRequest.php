<?php

declare(strict_types=1);

namespace App\Http\Requests\Dues;

use Illuminate\Contracts\Validation\ValidationRule;

class MoveDueRequest extends DuesRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'to_customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'note' => ['required', 'string', 'min:3', 'max:200'],
            'approver_username' => ['nullable', 'string', 'max:60'],
            'approver_secret' => ['nullable', 'string', 'max:100'],
        ];
    }
}
