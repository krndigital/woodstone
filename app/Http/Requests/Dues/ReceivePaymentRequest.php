<?php

declare(strict_types=1);

namespace App\Http\Requests\Dues;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ReceivePaymentRequest extends DuesRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::receivable()))],
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'reference' => ['nullable', 'string', 'max:60'],
            'qr_checked' => ['boolean', 'accepted_if:method,qr'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
