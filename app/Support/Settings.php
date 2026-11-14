<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * App settings stored as key → JSON value, with safe defaults (D-02: VAT and service charge 0%).
 */
final class Settings
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $fy = BsDate::fiscalYear(now());

        return [
            'restaurant_name' => 'Woodstone Restaurant & Cafe',
            'address' => 'Butwal-08, Sukkhanagar',
            'pan' => null,
            'phone' => null,
            'bill_footer' => 'Thank you! Please visit again.',
            'vat_percent' => '0',
            'service_charge_percent' => '0',
            'cashier_discount_limit_percent' => 10,
            'payment_qr' => true,
            'payment_card' => true,
            'payment_due' => true,
            'default_credit_limit' => '15000.00',
            'auto_print' => true,
            'guest_call_any_status' => false,
            'fiscal_year_label' => $fy['label'],
            'fiscal_year_starts_on' => $fy['starts_on'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $stored = DB::table('settings')->pluck('value', 'key')
                ->map(fn ($v) => is_string($v) ? json_decode($v, true) : $v)
                ->all();
            self::$cache = array_merge(self::defaults(), array_intersect_key($stored, self::defaults()));
        }

        return self::$cache;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function string(string $key): string
    {
        return (string) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    /**
     * Save changed values and write one audit row with before/after.
     *
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values, User $by): void
    {
        $before = self::all();
        $changed = [];

        DB::transaction(function () use ($values, $by, $before, &$changed): void {
            foreach (array_intersect_key($values, self::defaults()) as $key => $value) {
                if ($before[$key] === $value) {
                    continue;
                }
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => json_encode($value), 'updated_by' => $by->id, 'updated_at' => now(), 'created_at' => now()],
                );
                $changed[$key] = ['old' => $before[$key], 'new' => $value];
            }
        });

        self::flush();

        if ($changed !== []) {
            Audit::record('settings.updated', null, ['changes' => $changed], $by);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
