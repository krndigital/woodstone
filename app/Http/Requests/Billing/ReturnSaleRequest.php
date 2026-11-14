<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::TakePayment);
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'full_cancel' => ['boolean'],
            'amount' => ['nullable', 'required_unless:full_cancel,true,1', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'refund_method' => ['required', Rule::enum(PaymentMethod::class)],
            'restock' => ['boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
            'approver_username' => ['nullable', 'string', 'max:60'],
            'approver_secret' => ['nullable', 'string', 'max:100'],
        ];
    }
}
