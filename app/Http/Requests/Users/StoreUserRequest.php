<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Rules\StrongPin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        $actor = $this->user();
        $role = Role::tryFrom((string) $this->input('role'));

        return $actor instanceof User
            && $actor->hasPermission(Permission::ManageUsers)
            && ($role === null || $actor->role->canManageRole($role));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => mb_strtolower(trim((string) $this->input('username'))),
        ]);
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'username' => $this->usernameRules(),
            'email' => $this->emailRules(),
            'phone' => $this->phoneRules(),
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', Password::default()],
            'pin' => ['nullable', 'string', new StrongPin, Rule::prohibitedIf($this->input('role') === Role::SuperAdmin->value)],
        ];
    }
}
