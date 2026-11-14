<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Payables ageing by due date (FR-15.4). Payments and returns settle the oldest bills first.
 */
final class Payables
{
    /**
     * @return array<int, array{not_due: int, d1_30: int, d31_60: int, d61_90: int, d90_plus: int, next_7: int, owed: int}>
     */
    public static function ageing(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $bills = [];
        foreach (DB::table('suppliers')->where('opening_balance', '>', 0)->get(['id', 'opening_balance', 'created_at']) as $s) {
            $bills[(int) $s->id][] = ['amount' => Money::toPaisa((string) $s->opening_balance), 'due' => substr((string) $s->created_at, 0, 10)];
        }
        foreach (DB::table('purchases')->orderBy('purchased_on')->orderBy('id')->get(['supplier_id', 'total', 'paid_amount', 'purchased_on', 'due_on']) as $p) {
            $left = Money::toPaisa((string) $p->total) - Money::toPaisa((string) $p->paid_amount);
            if ($left > 0) {
                $bills[(int) $p->supplier_id][] = ['amount' => $left, 'due' => substr((string) ($p->due_on ?? $p->purchased_on), 0, 10)];
            }
        }
        $credit = [];
        foreach (DB::table('supplier_payments')->get(['supplier_id', 'amount']) as $r) {
            $credit[(int) $r->supplier_id] = ($credit[(int) $r->supplier_id] ?? 0) + Money::toPaisa((string) $r->amount);
        }
        foreach (DB::table('purchase_returns')->get(['supplier_id', 'amount']) as $r) {
            $credit[(int) $r->supplier_id] = ($credit[(int) $r->supplier_id] ?? 0) + Money::toPaisa((string) $r->amount);
        }

        $out = [];
        foreach ($bills as $supplierId => $list) {
            $c = $credit[$supplierId] ?? 0;
            $row = ['not_due' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90_plus' => 0, 'next_7' => 0, 'owed' => 0];
            foreach ($list as $b) {
                $used = min($c, $b['amount']);
                $c -= $used;
                $left = $b['amount'] - $used;
                if ($left <= 0) {
                    continue;
                }
                $due = CarbonImmutable::parse($b['due']);
                $late = (int) $due->diffInDays($today, false);
                $key = match (true) {
                    $late <= 0 => 'not_due',
                    $late <= 30 => 'd1_30',
                    $late <= 60 => 'd31_60',
                    $late <= 90 => 'd61_90',
                    default => 'd90_plus',
                };
                $row[$key] += $left;
                $row['owed'] += $left;
                if ($late <= 0 && $late >= -7) {
                    $row['next_7'] += $left;
                }
            }
            $row['owed'] -= $c; // overpaid suppliers show a credit
            $out[$supplierId] = $row;
        }

        return $out;
    }
}
