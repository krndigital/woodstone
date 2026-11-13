<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Rules\StrongPin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PinUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role->canUsePin() && $user->two_factor_confirmed_at === null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'pin' => ['required', 'string', new StrongPin, 'confirmed'],
        ];
    }
}
