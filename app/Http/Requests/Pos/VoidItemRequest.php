<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\ValidationRule;

class VoidItemRequest extends PosRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:200'],
            'approver_username' => ['nullable', 'string', 'max:60'],
            'approver_secret' => ['nullable', 'string', 'max:100'],
        ];
    }
}
