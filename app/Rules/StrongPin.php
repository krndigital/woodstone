<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A 4-digit PIN that is not trivially guessable (all same digits or a straight run like 1234 / 9876).
 */
class StrongPin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\d{4}$/', $value) !== 1) {
            $fail(__('The PIN must be exactly 4 digits.'));

            return;
        }

        $digits = array_map('intval', str_split($value));
        $diffs = [$digits[1] - $digits[0], $digits[2] - $digits[1], $digits[3] - $digits[2]];

        if (count(array_unique($diffs)) === 1 && in_array($diffs[0], [-1, 0, 1], true)) {
            $fail(__('This PIN is too easy to guess. Avoid 1111, 1234 or 4321.'));
        }
    }
}
