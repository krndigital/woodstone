<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettleRequest extends FormRequest
{
    public const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

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
            'discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'regex:'.self::MONEY],
            'discount_reason' => ['nullable', 'string', 'max:200'],
            'payments' => ['present', 'array', 'max:20'],
            'payments.*.payer' => ['nullable', 'string', 'max:40'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'regex:'.self::MONEY],
            'payments.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
            'payments.*.qr_checked' => ['boolean'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'approver_username' => ['nullable', 'string', 'max:60'],
            'approver_secret' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return list<array{payer: string|null, method: string, amount: string, customer_id: int|null, reference: string|null, qr_checked: bool}>
     */
    public function payments(): array
    {
        $out = [];
        foreach ((array) $this->validated('payments', []) as $p) {
            $p = (array) $p;
            $out[] = [
                'payer' => isset($p['payer']) && trim((string) $p['payer']) !== '' ? trim((string) $p['payer']) : null,
                'method' => (string) $p['method'],
                'amount' => (string) $p['amount'],
                'customer_id' => isset($p['customer_id']) ? (int) $p['customer_id'] : null,
                'reference' => isset($p['reference']) && trim((string) $p['reference']) !== '' ? trim((string) $p['reference']) : null,
                'qr_checked' => filter_var($p['qr_checked'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $out;
    }
}
