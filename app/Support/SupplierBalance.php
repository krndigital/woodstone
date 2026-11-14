<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * What we owe each supplier (FR-6.3, FR-15.1): unpaid part of purchases − supplier payments − returns.
 */
final class SupplierBalance
{
    /**
     * @return array<int, int> supplier id → paisa owed
     */
    public static function all(?string $asOf = null): array
    {
        $owed = [];
        foreach (DB::table('suppliers')->where('opening_balance', '!=', 0)->get(['id', 'opening_balance']) as $s) {
            $owed[(int) $s->id] = Money::toPaisa((string) $s->opening_balance);
        }
        foreach (DB::table('purchases')->when($asOf, fn ($q) => $q->where('purchased_on', '<=', $asOf))->get(['supplier_id', 'total', 'paid_amount']) as $p) {
            $owed[(int) $p->supplier_id] = ($owed[(int) $p->supplier_id] ?? 0) + Money::toPaisa((string) $p->total) - Money::toPaisa((string) $p->paid_amount);
        }
        foreach (DB::table('supplier_payments')->when($asOf, fn ($q) => $q->where('paid_on', '<=', $asOf))->get(['supplier_id', 'amount']) as $p) {
            $owed[(int) $p->supplier_id] = ($owed[(int) $p->supplier_id] ?? 0) - Money::toPaisa((string) $p->amount);
        }
        foreach (DB::table('purchase_returns')->when($asOf, fn ($q) => $q->whereDate('created_at', '<=', $asOf))->get(['supplier_id', 'amount']) as $p) {
            $owed[(int) $p->supplier_id] = ($owed[(int) $p->supplier_id] ?? 0) - Money::toPaisa((string) $p->amount);
        }

        return $owed;
    }

    public static function of(int $supplierId): int
    {
        return self::all()[$supplierId] ?? 0;
    }
}
