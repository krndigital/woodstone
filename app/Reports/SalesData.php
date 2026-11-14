<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\OrderItemStatus;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared sales figures for reports and the dashboard. Only paid bills count as sales;
 * returns are subtracted; the bill rows themselves never change.
 */
final class SalesData
{
    /**
     * @return array{bills: int, subtotal: int, discount: int, service: int, vat: int, total: int, returns: int, net: int, paid: int, due: int, covers: int}
     */
    public static function totals(Period $p): array
    {
        $r = ['bills' => 0, 'subtotal' => 0, 'discount' => 0, 'service' => 0, 'vat' => 0, 'total' => 0, 'returns' => 0, 'net' => 0, 'paid' => 0, 'due' => 0, 'covers' => 0];
        foreach (DB::table('bills')->join('orders', 'orders.id', '=', 'bills.order_id')->whereBetween('bills.created_at', $p->between())
            ->get(['bills.subtotal', 'bills.discount_amount', 'bills.service_amount', 'bills.vat_amount', 'bills.total', 'bills.paid_amount', 'bills.due_amount', 'orders.guests']) as $b) {
            $r['bills']++;
            $r['subtotal'] += Money::toPaisa((string) $b->subtotal);
            $r['discount'] += Money::toPaisa((string) $b->discount_amount);
            $r['service'] += Money::toPaisa((string) $b->service_amount);
            $r['vat'] += Money::toPaisa((string) $b->vat_amount);
            $r['total'] += Money::toPaisa((string) $b->total);
            $r['paid'] += Money::toPaisa((string) $b->paid_amount);
            $r['due'] += Money::toPaisa((string) $b->due_amount);
            $r['covers'] += (int) $b->guests;
        }
        $r['returns'] = self::returns($p);
        $r['net'] = $r['total'] - $r['returns'];

        return $r;
    }

    public static function returns(Period $p): int
    {
        return Money::fromDbSum(DB::table('sales_returns')->whereBetween('created_at', $p->between())->sum('amount'));
    }

    /**
     * Cost of goods sold from stock movements (sale cost at the average cost of that moment),
     * less ingredients put back on cancelled bills.
     */
    public static function cogs(Period $p, ?string $stationKind = null): int
    {
        $cost4 = 0;
        $q = DB::table('stock_movements')->whereBetween('stock_movements.created_at', $p->between())
            ->where(fn ($w) => $w->where('stock_movements.type', 'sale')->orWhere(fn ($x) => $x->where('stock_movements.type', 'return')->where('stock_movements.reference_type', 'sales_return')));
        foreach ($q->get(['stock_movements.quantity', 'stock_movements.unit_cost', 'stock_movements.stock_item_id']) as $m) {
            $cost4 += -Qty::div(Qty::toMilli((string) $m->quantity) * Qty::toCost((string) ($m->unit_cost ?? '0')), 1000);
        }
        if ($stationKind !== null) {
            // Split total COGS by each station's share of recipe cost (estimate).
            $share = self::stationShares($p);

            return Qty::div(Qty::div($cost4, 100) * ($share[$stationKind] ?? 0), 10000);
        }

        return Qty::div($cost4, 100);
    }

    /**
     * Revenue share per station kind in basis points (kitchen / bar / counter).
     *
     * @return array<string, int>
     */
    public static function stationShares(Period $p): array
    {
        $rev = self::revenueByStationKind($p);
        $sum = array_sum($rev);
        $out = [];
        foreach ($rev as $kind => $v) {
            $out[$kind] = $sum === 0 ? 0 : intdiv($v * 10000, $sum);
        }

        return $out;
    }

    /**
     * @return array<string, int> station kind → paisa (item revenue before bill discount)
     */
    public static function revenueByStationKind(Period $p): array
    {
        $out = [];
        foreach (self::soldLines($p)->join('stations', 'stations.id', '=', 'order_items.station_id')->get(['stations.kind', 'order_items.line_total']) as $l) {
            $out[(string) $l->kind] = ($out[(string) $l->kind] ?? 0) + Money::toPaisa((string) $l->line_total);
        }

        return $out;
    }

    /**
     * Order lines on bills paid in the period.
     */
    public static function soldLines(Period $p): Builder
    {
        return DB::table('order_items')
            ->join('bills', 'bills.order_id', '=', 'order_items.order_id')
            ->whereBetween('bills.created_at', $p->between())
            ->where('order_items.status', '!=', OrderItemStatus::Void->value);
    }

    public static function expenses(Period $p, ?string $group = null, string $kind = 'operating'): int
    {
        return Money::fromDbSum(DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expenses.spent_on', [$p->fromDate(), $p->toDate()])
            ->whereNull('expenses.voided_at')
            ->where('expenses.kind', $kind)
            ->when($group !== null, fn ($q) => $q->where('expense_categories.group', $group))
            ->sum('expenses.amount'));
    }

    /**
     * @return list<array{name: string, qty: int, revenue: int}>
     */
    public static function topItems(Period $p, int $limit = 5): array
    {
        $items = [];
        foreach (self::soldLines($p)->get(['order_items.menu_item_id', 'order_items.name', 'order_items.quantity', 'order_items.line_total']) as $l) {
            $id = (int) $l->menu_item_id;
            $items[$id] ??= ['name' => (string) $l->name, 'qty' => 0, 'revenue' => 0];
            $items[$id]['qty'] += (int) $l->quantity;
            $items[$id]['revenue'] += Money::toPaisa((string) $l->line_total);
        }
        usort($items, fn (array $a, array $b): int => $b['qty'] <=> $a['qty'] ?: $b['revenue'] <=> $a['revenue']);

        return array_slice($items, 0, $limit);
    }
}
