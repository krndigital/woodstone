<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Money helpers. Amounts are kept as DECIMAL(12,2) strings in the database and as integer paisa
 * (1 rupee = 100 paisa) for any maths, so there is never a floating-point rounding error.
 */
final class Money
{
    /**
     * "220", "220.5", "220.50" → 22050.
     */
    public static function toPaisa(string|int $amount): int
    {
        $value = trim((string) $amount);

        if (preg_match('/^(-)?(\d{1,10})(?:\.(\d{1,2}))?$/', $value, $m) !== 1) {
            throw new InvalidArgumentException("Not a money amount: {$value}");
        }

        $paisa = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$paisa : $paisa;
    }

    /**
     * 22050 → "220.50" (database / API format).
     */
    public static function fromPaisa(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';
        $abs = abs($paisa);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Converts a SQL SUM() result (exact numeric string on PostgreSQL, float on SQLite) to paisa.
     */
    public static function fromDbSum(mixed $sum): int
    {
        if ($sum === null || $sum === '') {
            return 0;
        }
        if (is_string($sum) && preg_match('/^-?\d+(\.\d+)?$/', $sum) === 1) {
            [$whole, $frac] = array_pad(explode('.', ltrim($sum, '-')), 2, '');
            $paisa = ((int) $whole) * 100 + (int) str_pad(substr($frac, 0, 2), 2, '0');
            $paisa += (int) ((int) substr($frac, 2, 1) >= 5);

            return str_starts_with($sum, '-') ? -$paisa : $paisa;
        }

        return (int) round(((float) $sum) * 100);
    }

    /**
     * Gross margin as a whole percent of price, or null when unknown.
     */
    public static function marginPercent(?string $price, ?string $cost): ?int
    {
        if ($price === null || $cost === null) {
            return null;
        }

        $p = self::toPaisa($price);

        if ($p <= 0) {
            return null;
        }

        return intdiv(($p - self::toPaisa($cost)) * 100, $p);
    }
}
