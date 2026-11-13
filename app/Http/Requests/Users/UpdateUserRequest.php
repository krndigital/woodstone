<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use App\Rules\StrongPin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function target(): User
    {
        $target = $this->route('user');
        assert($target instanceof User);

        return $target;
    }

    public function authorize(): bool
    {
        $actor = $this->user();
        $role = Role::tryFrom((string) $this->input('role'));

        return $actor instanceof User
            && $actor->can('update', $this->target())
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
            'username' => $this->usernameRules($this->target()->id),
            'email' => $this->emailRules($this->target()->id),
            'phone' => $this->phoneRules(),
            'role' => ['required', Rule::enum(Role::class)],
            'pin' => ['nullable', 'string', new StrongPin, Rule::prohibitedIf($this->input('role') === Role::SuperAdmin->value)],
        ];
    }

    /**
     * Nobody changes their own role (prevents self-promotion and accidental lock-out).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                if ($actor instanceof User && $actor->is($this->target()) && $this->input('role') !== $actor->role->value) {
                    $validator->errors()->add('role', __('You cannot change your own role.'));
                }
            },
        ];
    }
}
