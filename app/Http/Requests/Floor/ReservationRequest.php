<?php

declare(strict_types=1);

namespace App\Http\Requests\Floor;

use App\Enums\Permission;
use App\Http\Requests\Money\PermissionRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ReservationRequest extends PermissionRequest
{
    protected function permission(): Permission
    {
        return Permission::ViewFloor;
    }

    public function authorize(): bool
    {
        // Taking or refunding advance money needs the cashier permission.
        $money = $this->filled('advance') && $this->input('advance') !== '0' || $this->filled('refund_method');

        return parent::authorize() && (! $money || $this->actor()->hasPermission(Permission::TakePayment));
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        if ($this->route()?->getName() === 'floor.reservations.close') {
            return ['status' => ['required', Rule::in(['cancelled', 'no_show'])], 'refund_method' => ['nullable', Rule::in(['cash', 'qr', 'card', 'bank'])]];
        }

        return [
            'dining_table_id' => ['required', 'integer', 'exists:dining_tables,id'],
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\- ]{7,20}$/'],
            'reserved_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'guests' => ['required', 'integer', 'min:1', 'max:60'],
            'advance' => ['nullable', 'regex:'.self::MONEY],
            'advance_method' => ['nullable', Rule::in(['cash', 'qr', 'card', 'bank'])],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
