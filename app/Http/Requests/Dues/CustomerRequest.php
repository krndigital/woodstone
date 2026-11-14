<?php

declare(strict_types=1);

namespace App\Http\Requests\Dues;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class CustomerRequest extends DuesRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => preg_replace('/[\s-]+/', '', $this->input('phone'))]);
        }
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{7,15}$/', Rule::unique('customers', 'phone')->ignore($customer instanceof Customer ? $customer->id : null)],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'note' => ['nullable', 'string', 'max:200'],
            'credit_limit' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'is_active' => ['boolean'],
            'email' => ['nullable', 'email:rfc', 'max:120'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'anniversary' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'preferred_seat' => ['nullable', 'string', 'max:40'],
            'opening_balance' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ];
    }
}
