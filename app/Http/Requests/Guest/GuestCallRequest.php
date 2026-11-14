<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public request from the QR page. No login; protected by the seat token, a per-IP rate limit
 * and one call per seat per 60 seconds (FR-16.4).
 */
class GuestCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['waiter', 'bill', 'water', 'help'])],
            'note' => ['nullable', 'string', 'max:120'],
        ];
    }
}
