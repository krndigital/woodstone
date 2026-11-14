<?php

declare(strict_types=1);

namespace App\Http\Requests\Floor;

use App\Enums\Permission;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TableStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ViewFloor);
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([TableStatus::Free->value, TableStatus::Reserved->value])],
            'reserved_for' => ['nullable', 'required_if:status,reserved', 'string', 'max:80'],
            'reserved_at' => ['nullable', 'required_if:status,reserved', 'date'],
        ];
    }

    /**
     * Only allowed hand changes: free → reserved, reserved → free, cleaning → free.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $table = $this->route('table');
                $target = TableStatus::tryFrom((string) $this->input('status'));

                if ($table instanceof DiningTable && $target !== null && ! in_array($target, $table->status->manualTargets(), true)) {
                    $validator->errors()->add('status', __(':table is :status and cannot be set to :target by hand.', [
                        'table' => $table->code, 'status' => $table->status->label(), 'target' => $target->label(),
                    ]));
                }
            },
        ];
    }
}
