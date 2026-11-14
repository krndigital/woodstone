<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free counters. The row is locked (SELECT … FOR UPDATE on PostgreSQL) so two cashiers can
 * never get the same bill number. Must be called inside the caller's transaction.
 */
final class NumberSequence
{
    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key): int {
            DB::table('number_sequences')->insertOrIgnore([
                'key' => $key, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();
            $value = (int) ($row->next_value ?? 1);

            DB::table('number_sequences')->where('key', $key)->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return $value;
        });
    }

    /**
     * Bill number for the current fiscal year, e.g. "2083/84-00012" (restarts each year).
     */
    public static function nextBillNumber(): string
    {
        $fy = Settings::string('fiscal_year_label');

        return $fy.'-'.str_pad((string) self::next('bill:'.$fy), 5, '0', STR_PAD_LEFT);
    }
}
