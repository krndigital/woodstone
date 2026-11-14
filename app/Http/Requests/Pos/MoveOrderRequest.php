<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\ValidationRule;

class MoveOrderRequest extends PosRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return ['dining_table_id' => ['required', 'integer', 'exists:dining_tables,id']];
    }
}
