<?php

declare(strict_types=1);

namespace App\Http\Requests\Dues;

use Illuminate\Contracts\Validation\ValidationRule;

class CustomerGroupRequest extends DuesRequest
{
    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:60', 'unique:customer_groups,name']];
    }
}
