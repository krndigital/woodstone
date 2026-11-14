<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Stock quantities as integer thousandths ("1.5" kg → 1500) and unit costs as integer
 * ten-thousandths of a rupee ("12.3456" → 123456), so stock maths never uses floats.
 */
final class Qty
{
    public static function toMilli(string|int $value): int
    {
        return self::parse((string) $value, 3);
    }

    public static function fromMilli(int $milli): string
    {
        return self::format($milli, 3);
    }

    public static function toCost(string|int $value): int
    {
        return self::parse((string) $value, 4);
    }

    public static function fromCost(int $cost): string
    {
        return self::format($cost, 4);
    }

    /**
     * Rounded half away from zero integer division.
     */
    public static function div(int $a, int $b): int
    {
        if ($b === 0) {
            return 0;
        }
        $q = intdiv($a, $b);
        $r = $a % $b;

        return abs($r) * 2 >= abs($b) ? $q + (($a < 0) === ($b < 0) ? 1 : -1) : $q;
    }

    private static function parse(string $value, int $scale): int
    {
        $value = trim($value);
        if (preg_match('/^(-)?(\d{1,12})(?:\.(\d{1,'.$scale.'}))?$/', $value, $m) !== 1) {
            throw new InvalidArgumentException("Not a number with up to {$scale} decimals: {$value}");
        }
        $n = ((int) $m[2]) * (10 ** $scale) + (int) str_pad($m[3] ?? '0', $scale, '0');

        return $m[1] === '-' ? -$n : $n;
    }

    private static function format(int $n, int $scale): string
    {
        $sign = $n < 0 ? '-' : '';
        $abs = abs($n);
        $base = 10 ** $scale;

        return $sign.intdiv($abs, $base).'.'.str_pad((string) ($abs % $base), $scale, '0', STR_PAD_LEFT);
    }
}
