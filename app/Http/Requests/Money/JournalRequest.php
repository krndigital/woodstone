<?php

declare(strict_types=1);

namespace App\Http\Requests\Money;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Manual journal entry: Super Admin only (FR-13.4).
 */
class JournalRequest extends PermissionRequest
{
    protected function permission(): Permission
    {
        return Permission::ManageSettings;
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'memo' => ['required', 'string', 'min:3', 'max:200'],
            'lines' => ['required', 'array', 'min:2', 'max:20'],
            'lines.*.code' => ['required', 'string', 'exists:gl_accounts,code'],
            'lines.*.debit' => ['nullable', 'regex:'.self::MONEY],
            'lines.*.credit' => ['nullable', 'regex:'.self::MONEY],
        ];
    }
}
