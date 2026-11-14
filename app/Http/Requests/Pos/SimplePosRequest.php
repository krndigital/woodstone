<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * POS actions with no input (send KOT, remove draft line, mark served).
 */
class SimplePosRequest extends PosRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [];
    }
}
