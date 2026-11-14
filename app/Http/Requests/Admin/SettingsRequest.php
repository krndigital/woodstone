<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    private const PERCENT = '/^\d{1,3}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageSettings);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'restaurant_name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:150'],
            'pan' => ['nullable', 'string', 'regex:/^\d{9}$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bill_footer' => ['nullable', 'string', 'max:120'],
            'vat_percent' => ['required', 'regex:'.self::PERCENT, 'numeric', 'min:0', 'max:20'],
            'service_charge_percent' => ['required', 'regex:'.self::PERCENT, 'numeric', 'min:0', 'max:20'],
            'cashier_discount_limit_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'payment_qr' => ['boolean'],
            'payment_card' => ['boolean'],
            'payment_due' => ['boolean'],
            'default_credit_limit' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'auto_print' => ['boolean'],
            'guest_call_any_status' => ['boolean'],
            'fiscal_year_label' => ['required', 'regex:/^\d{4}\/\d{2}$/'],
            'fiscal_year_starts_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pan.regex' => 'PAN must be 9 digits.',
            'fiscal_year_label.regex' => 'Use the format 2083/84.',
        ];
    }
}
