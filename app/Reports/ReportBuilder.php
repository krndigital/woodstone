<?php

declare(strict_types=1);

namespace App\Reports;

use App\Actions\Staff\Payroll;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Customer;
use App\Models\DayClose;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Support\BsDate;
use App\Support\CashBook;
use App\Support\DuesData;
use App\Support\Money;
use App\Support\Qty;
use App\Support\SupplierBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Builds one report as plain columns + rows (money as paisa ints, shown and exported the same way).
 * Column types: text | int | money | percent | qty.
 */
final class ReportBuilder
{
    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    public static function build(string $key, Period $p): array
    {
        return match ($key) {
            'daily-sales' => self::dailySales($p),
            'items' => self::items($p),
            'categories' => self::categories($p),
            'hourly' => self::hourly($p),
            'tables' => self::tables($p),
            'sales-vs-purchase' => self::salesVsPurchase($p),
            'expenses' => self::expenses($p),
            'cash-book' => self::cashBook($p),
            'day-close' => self::dayClose($p),
            'customer-credit' => self::customerCredit(),
            'supplier-payable' => self::supplierPayable(),
            'stock' => self::stock($p),
            'staff' => self::staff($p),
            'profit-loss' => self::profitLoss($p),
            'audit' => self::audit($p),
            default => ['columns' => [], 'rows' => [], 'totals' => null, 'notes' => []],
        };
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: string}>  $cols
     * @return list<array{key: string, label: string, type: string}>
     */
    private static function cols(array $cols): array
    {
        return array_map(fn (array $c): array => ['key' => $c[0], 'label' => $c[1], 'type' => $c[2] ?? 'text'], $cols);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function sum(array $rows, array $keys, string $labelKey, string $label = 'Total'): array
    {
        $t = [$labelKey => $label];
        foreach ($keys as $k) {
            $t[$k] = array_sum(array_map(fn (array $r): int => (int) ($r[$k] ?? 0), $rows));
        }

        return $t;
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function dailySales(Period $p): array
    {
        $days = [];
        $blank = ['bills' => 0, 'subtotal' => 0, 'discount' => 0, 'service' => 0, 'vat' => 0, 'total' => 0, 'cash' => 0, 'qr' => 0, 'card' => 0, 'due' => 0, 'returns' => 0];
        foreach (DB::table('bills')->whereBetween('created_at', $p->between())->get(['id', 'created_at', 'subtotal', 'discount_amount', 'service_amount', 'vat_amount', 'total']) as $b) {
            $d = substr((string) $b->created_at, 0, 10);
            $days[$d] ??= ['date' => $d, ...$blank];
            $days[$d]['bills']++;
            $days[$d]['subtotal'] += Money::toPaisa((string) $b->subtotal);
            $days[$d]['discount'] += Money::toPaisa((string) $b->discount_amount);
            $days[$d]['service'] += Money::toPaisa((string) $b->service_amount);
            $days[$d]['vat'] += Money::toPaisa((string) $b->vat_amount);
            $days[$d]['total'] += Money::toPaisa((string) $b->total);
        }
        foreach (DB::table('bill_payments')->join('bills', 'bills.id', '=', 'bill_payments.bill_id')->whereBetween('bills.created_at', $p->between())->get(['bills.created_at', 'bill_payments.method', 'bill_payments.amount']) as $pay) {
            $d = substr((string) $pay->created_at, 0, 10);
            $method = (string) $pay->method;
            if (isset($days[$d]) && array_key_exists($method, $blank)) {
                $days[$d][$method] += Money::toPaisa((string) $pay->amount);
            }
        }
        foreach (DB::table('sales_returns')->whereBetween('created_at', $p->between())->get(['created_at', 'amount']) as $ret) {
            $d = substr((string) $ret->created_at, 0, 10);
            $days[$d] ??= ['date' => $d, ...$blank];
            $days[$d]['returns'] += Money::toPaisa((string) $ret->amount);
        }
        ksort($days);
        $rows = array_values(array_map(fn (array $r): array => [...$r, 'bs' => BsDate::format(CarbonImmutable::parse($r['date'])), 'net' => $r['total'] - $r['returns']], $days));
        $keys = ['bills', 'subtotal', 'discount', 'service', 'vat', 'total', 'cash', 'qr', 'card', 'due', 'returns', 'net'];

        return [
            'columns' => self::cols([['bs', 'Date (BS)'], ['date', 'Date (AD)'], ['bills', 'Bills', 'int'], ['subtotal', 'Subtotal', 'money'], ['discount', 'Discount', 'money'], ['service', 'Service', 'money'], ['vat', 'VAT', 'money'], ['total', 'Billed', 'money'], ['cash', 'Cash', 'money'], ['qr', 'QR', 'money'], ['card', 'Card', 'money'], ['due', 'On due', 'money'], ['returns', 'Returns', 'money'], ['net', 'Net sales', 'money']]),
            'rows' => $rows,
            'totals' => self::sum($rows, $keys, 'bs'),
            'notes' => [],
        ];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function items(Period $p): array
    {
        $cost = self::recipeCosts();
        $items = [];
        foreach (SalesData::soldLines($p)->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')->leftJoin('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->get(['order_items.menu_item_id', 'order_items.name', 'order_items.quantity', 'order_items.line_total', 'menu_categories.name as category']) as $l) {
            $id = (int) $l->menu_item_id;
            $items[$id] ??= ['item' => (string) $l->name, 'category' => (string) ($l->category ?? ''), 'qty' => 0, 'revenue' => 0, 'cost' => 0];
            $items[$id]['qty'] += (int) $l->quantity;
            $items[$id]['revenue'] += Money::toPaisa((string) $l->line_total);
            $items[$id]['cost'] += ($cost[$id] ?? 0) * (int) $l->quantity;
        }
        $rows = [];
        foreach ($items as $i) {
            $profit = $i['revenue'] - $i['cost'];
            $rows[] = [...$i, 'profit' => $profit, 'margin' => $i['revenue'] > 0 ? intdiv($profit * 10000, $i['revenue']) : 0];
        }
        usort($rows, fn (array $a, array $b): int => $b['qty'] <=> $a['qty']);
        $totals = self::sum($rows, ['qty', 'revenue', 'cost', 'profit'], 'item');
        $totals['margin'] = $totals['revenue'] > 0 ? intdiv((int) $totals['profit'] * 10000, (int) $totals['revenue']) : 0;

        return [
            'columns' => self::cols([['item', 'Item'], ['category', 'Category'], ['qty', 'Qty', 'int'], ['revenue', 'Revenue', 'money'], ['cost', 'Food cost', 'money'], ['profit', 'Profit', 'money'], ['margin', 'Margin', 'percent']]),
            'rows' => $rows,
            'totals' => $totals,
            'notes' => ['Sorted by quantity: top rows are most ordered, bottom rows least ordered.', 'Food cost uses each recipe at today\'s average cost; items without a recipe show no cost.'],
        ];
    }

    /**
     * @return array<int, int> menu item id → cost per plate in paisa
     */
    private static function recipeCosts(): array
    {
        $out = [];
        foreach (DB::table('recipe_lines')->join('stock_items', 'stock_items.id', '=', 'recipe_lines.stock_item_id')->get(['recipe_lines.menu_item_id', 'recipe_lines.quantity', 'stock_items.avg_cost']) as $r) {
            $out[(int) $r->menu_item_id] = ($out[(int) $r->menu_item_id] ?? 0) + Qty::div(Qty::toMilli((string) $r->quantity) * Qty::toCost((string) $r->avg_cost), 100000);
        }

        return $out;
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function categories(Period $p): array
    {
        $cats = [];
        foreach (SalesData::soldLines($p)->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')->leftJoin('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->get(['menu_categories.name as category', 'order_items.quantity', 'order_items.line_total']) as $l) {
            $c = (string) ($l->category ?? 'Other');
            $cats[$c] ??= ['category' => $c, 'qty' => 0, 'revenue' => 0];
            $cats[$c]['qty'] += (int) $l->quantity;
            $cats[$c]['revenue'] += Money::toPaisa((string) $l->line_total);
        }
        $rows = array_values($cats);
        usort($rows, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
        $sum = array_sum(array_column($rows, 'revenue'));
        $rows = array_map(fn (array $r): array => [...$r, 'share' => $sum > 0 ? intdiv($r['revenue'] * 10000, $sum) : 0], $rows);

        return ['columns' => self::cols([['category', 'Category'], ['qty', 'Qty', 'int'], ['revenue', 'Revenue', 'money'], ['share', 'Share', 'percent']]), 'rows' => $rows, 'totals' => self::sum($rows, ['qty', 'revenue'], 'category'), 'notes' => []];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function hourly(Period $p): array
    {
        $hours = [];
        foreach (DB::table('bills')->whereBetween('created_at', $p->between())->get(['created_at', 'total']) as $b) {
            $h = (int) substr((string) $b->created_at, 11, 2);
            $hours[$h] ??= ['hour' => sprintf('%02d:00–%02d:59', $h, $h), 'bills' => 0, 'sales' => 0];
            $hours[$h]['bills']++;
            $hours[$h]['sales'] += Money::toPaisa((string) $b->total);
        }
        ksort($hours);
        $rows = array_values($hours);

        return ['columns' => self::cols([['hour', 'Hour'], ['bills', 'Bills', 'int'], ['sales', 'Sales', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['bills', 'sales'], 'hour'), 'notes' => []];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function tables(Period $p): array
    {
        $seats = [];
        foreach (DB::table('bills')->join('orders', 'orders.id', '=', 'bills.order_id')->leftJoin('dining_tables', 'dining_tables.id', '=', 'orders.dining_table_id')->leftJoin('zones', 'zones.id', '=', 'dining_tables.zone_id')
            ->whereBetween('bills.created_at', $p->between())->get(['orders.type', 'dining_tables.code', 'zones.name as zone', 'orders.guests', 'bills.total']) as $b) {
            $key = $b->code !== null ? (string) $b->code : ucfirst(str_replace('_', ' ', (string) $b->type));
            $seats[$key] ??= ['seat' => $key, 'zone' => (string) ($b->zone ?? '—'), 'bills' => 0, 'covers' => 0, 'sales' => 0];
            $seats[$key]['bills']++;
            $seats[$key]['covers'] += (int) $b->guests;
            $seats[$key]['sales'] += Money::toPaisa((string) $b->total);
        }
        $rows = array_values(array_map(fn (array $r): array => [...$r, 'avg' => intdiv($r['sales'], $r['bills'])], $seats));
        usort($rows, fn (array $a, array $b): int => $b['sales'] <=> $a['sales']);

        return ['columns' => self::cols([['seat', 'Seat'], ['zone', 'Zone'], ['bills', 'Bills', 'int'], ['covers', 'Covers', 'int'], ['sales', 'Sales', 'money'], ['avg', 'Average bill', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['bills', 'covers', 'sales'], 'seat'), 'notes' => []];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function salesVsPurchase(Period $p): array
    {
        $days = [];
        foreach (DB::table('bills')->whereBetween('created_at', $p->between())->get(['created_at', 'total']) as $b) {
            $d = substr((string) $b->created_at, 0, 10);
            $days[$d] ??= ['date' => $d, 'sales' => 0, 'purchases' => 0];
            $days[$d]['sales'] += Money::toPaisa((string) $b->total);
        }
        foreach (DB::table('purchases')->whereBetween('purchased_on', [$p->fromDate(), $p->toDate()])->get(['purchased_on', 'total']) as $pu) {
            $d = substr((string) $pu->purchased_on, 0, 10);
            $days[$d] ??= ['date' => $d, 'sales' => 0, 'purchases' => 0];
            $days[$d]['purchases'] += Money::toPaisa((string) $pu->total);
        }
        ksort($days);
        $rows = array_values(array_map(fn (array $r): array => [...$r, 'difference' => $r['sales'] - $r['purchases']], $days));

        return ['columns' => self::cols([['date', 'Date'], ['sales', 'Sales', 'money'], ['purchases', 'Purchases', 'money'], ['difference', 'Difference', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['sales', 'purchases', 'difference'], 'date'), 'notes' => []];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function expenses(Period $p): array
    {
        $cats = [];
        foreach (DB::table('expenses')->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->join('accounts', 'accounts.id', '=', 'expenses.account_id')
            ->whereBetween('expenses.spent_on', [$p->fromDate(), $p->toDate()])->whereNull('expenses.voided_at')
            ->get(['expense_categories.name', 'expenses.kind', 'expenses.amount', 'accounts.code']) as $e) {
            $c = (string) $e->name;
            $cats[$c] ??= ['category' => $c, 'kind' => $e->kind === 'investment' ? 'Investment' : 'Operating', 'entries' => 0, 'cash' => 0, 'other' => 0, 'amount' => 0];
            $a = Money::toPaisa((string) $e->amount);
            $cats[$c]['entries']++;
            $cats[$c][$e->code === 'cash' ? 'cash' : 'other'] += $a;
            $cats[$c]['amount'] += $a;
        }
        $rows = array_values($cats);
        usort($rows, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return ['columns' => self::cols([['category', 'Category'], ['kind', 'Type'], ['entries', 'Entries', 'int'], ['cash', 'From cash', 'money'], ['other', 'Bank / wallet', 'money'], ['amount', 'Total', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['entries', 'cash', 'other', 'amount'], 'category'), 'notes' => ['Void entries are not counted.']];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function cashBook(Period $p): array
    {
        $rows = [];
        foreach (Account::query()->orderBy('id')->get() as $a) {
            $opening = CashBook::balance($a, $p->from->subDay()->toDateString());
            $closing = CashBook::balance($a, $p->toDate());
            $in = Money::fromDbSum(DB::table('account_transactions')->where('account_id', $a->id)->whereBetween('business_date', [$p->fromDate(), $p->toDate()])->where('amount', '>', 0)->sum('amount'));
            $rows[] = ['account' => $a->name, 'opening' => $opening, 'in' => $in, 'out' => $in - ($closing - $opening), 'closing' => $closing];
        }

        return ['columns' => self::cols([['account', 'Account'], ['opening', 'Opening', 'money'], ['in', 'Money in', 'money'], ['out', 'Money out', 'money'], ['closing', 'Closing', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['opening', 'in', 'out', 'closing'], 'account'), 'notes' => []];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function dayClose(Period $p): array
    {
        $rows = array_values(DayClose::query()->with(['opener:id,name', 'closer:id,name'])->whereBetween('business_date', [$p->fromDate(), $p->toDate()])->orderBy('business_date')->get()
            ->map(fn (DayClose $d): array => [
                'date' => $d->business_date->toDateString(), 'opened_by' => $d->opener->name, 'closed_by' => $d->closer->name ?? 'Still open',
                'opening' => Money::toPaisa($d->opening_cash), 'expected' => Money::toPaisa($d->expected_cash ?? '0'),
                'counted' => Money::toPaisa($d->counted_cash ?? '0'), 'difference' => Money::toPaisa($d->difference ?? '0'),
            ])->all());

        return ['columns' => self::cols([['date', 'Date'], ['opened_by', 'Opened by'], ['closed_by', 'Closed by'], ['opening', 'Opening', 'money'], ['expected', 'Expected', 'money'], ['counted', 'Counted', 'money'], ['difference', 'Difference', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['difference'], 'date'), 'notes' => []];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function customerCredit(): array
    {
        $rows = [];
        foreach (Customer::query()->orderBy('name')->get() as $c) {
            $s = DuesData::summary($c);
            if ($s['balance'] === 0) {
                continue;
            }
            $rows[] = ['customer' => $c->name, 'phone' => $c->phone ?? '', 'limit' => Money::toPaisa($c->credit_limit), 'balance' => $s['balance'], 'age_0_30' => $s['age_0_30'], 'age_31_60' => $s['age_31_60'], 'age_60_plus' => $s['age_60_plus']];
        }
        usort($rows, fn (array $a, array $b): int => $b['balance'] <=> $a['balance']);

        return ['columns' => self::cols([['customer', 'Customer'], ['phone', 'Phone'], ['limit', 'Limit', 'money'], ['balance', 'Due', 'money'], ['age_0_30', '0–30 days', 'money'], ['age_31_60', '31–60 days', 'money'], ['age_60_plus', '60+ days', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['balance', 'age_0_30', 'age_31_60', 'age_60_plus'], 'customer'), 'notes' => ['As of today.']];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function supplierPayable(): array
    {
        $owed = SupplierBalance::all();
        $rows = [];
        foreach (Supplier::query()->orderBy('name')->get() as $s) {
            if (($owed[$s->id] ?? 0) !== 0) {
                $rows[] = ['supplier' => $s->name, 'phone' => $s->phone ?? '', 'pan' => $s->pan ?? '', 'owed' => $owed[$s->id]];
            }
        }

        return ['columns' => self::cols([['supplier', 'Supplier'], ['phone', 'Phone'], ['pan', 'PAN'], ['owed', 'We owe', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['owed'], 'supplier'), 'notes' => ['As of today.']];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function stock(Period $p): array
    {
        $moves = [];
        foreach (DB::table('stock_movements')->whereBetween('created_at', $p->between())->get(['stock_item_id', 'type', 'quantity']) as $m) {
            $moves[(int) $m->stock_item_id][(string) $m->type] = ($moves[(int) $m->stock_item_id][(string) $m->type] ?? 0) + Qty::toMilli((string) $m->quantity);
        }
        $rows = [];
        foreach (StockItem::query()->where('is_active', true)->orderBy('name')->get() as $i) {
            $onHand = Qty::toMilli($i->on_hand);
            $m = $moves[$i->id] ?? [];
            $rows[] = [
                'item' => $i->name, 'unit' => $i->unit, 'purchased' => $m['purchase'] ?? 0, 'used' => -($m['sale'] ?? 0),
                'wastage' => -(($m['wastage'] ?? 0) + ($m['staff_meal'] ?? 0)), 'on_hand' => $onHand,
                'value' => Qty::div($onHand * Qty::toCost($i->avg_cost), 100000), 'low' => $onHand <= Qty::toMilli($i->reorder_level) ? 'LOW' : '',
            ];
        }

        return ['columns' => self::cols([['item', 'Item'], ['unit', 'Unit'], ['purchased', 'Bought', 'qty'], ['used', 'Used in sales', 'qty'], ['wastage', 'Wastage + staff meal', 'qty'], ['on_hand', 'On hand now', 'qty'], ['value', 'Value now', 'money'], ['low', 'Alert']]), 'rows' => $rows, 'totals' => self::sum($rows, ['value'], 'item'), 'notes' => ['Bought / used / wastage are for the selected period; on hand and value are as of now.']];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function staff(Period $p): array
    {
        $sales = [];
        foreach (DB::table('bills')->join('orders', 'orders.id', '=', 'bills.order_id')->whereBetween('bills.created_at', $p->between())->get(['orders.waiter_id', 'bills.total']) as $b) {
            $sales[(int) $b->waiter_id] ??= ['bills' => 0, 'sales' => 0];
            $sales[(int) $b->waiter_id]['bills']++;
            $sales[(int) $b->waiter_id]['sales'] += Money::toPaisa((string) $b->total);
        }
        $month = $p->from->format('Y-m');
        $rows = [];
        foreach (Staff::query()->where('is_active', true)->orderBy('name')->get() as $s) {
            $a = Payroll::summary($s, $month, $p->to);
            $sale = $s->user_id !== null ? ($sales[$s->user_id] ?? ['bills' => 0, 'sales' => 0]) : ['bills' => 0, 'sales' => 0];
            $rows[] = ['name' => $s->name, 'position' => $s->position, 'present' => $a['present'], 'late' => $a['late'], 'leave' => $a['leave'], 'absent' => $a['absent'], 'bills' => $sale['bills'], 'sales' => $sale['sales']];
        }

        return ['columns' => self::cols([['name', 'Staff'], ['position', 'Position'], ['present', 'Present', 'int'], ['late', 'Late', 'int'], ['leave', 'Leave', 'int'], ['absent', 'Absent', 'int'], ['bills', 'Bills served', 'int'], ['sales', 'Sales', 'money']]), 'rows' => $rows, 'totals' => self::sum($rows, ['bills', 'sales'], 'name'), 'notes' => ['Attendance is for the month of the start date; sales need the staff member linked to their login.']];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function profitLoss(Period $p): array
    {
        $t = SalesData::totals($p);
        $income = $t['net'] - $t['vat'];
        $cogs = SalesData::cogs($p);
        $rows = [
            ['line' => 'Sales billed (incl. service, excl. VAT)', 'amount' => $t['total'] - $t['vat']],
            ['line' => 'Less returns', 'amount' => -$t['returns']],
            ['line' => 'Income', 'amount' => $income],
            ['line' => 'Cost of goods (ingredients used)', 'amount' => -$cogs],
            ['line' => 'Gross profit', 'amount' => $income - $cogs],
        ];
        $expenses = 0;
        foreach (DB::table('expenses')->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->whereBetween('expenses.spent_on', [$p->fromDate(), $p->toDate()])->whereNull('expenses.voided_at')->where('expenses.kind', 'operating')
            ->groupBy('expense_categories.name')->orderBy('expense_categories.name')->selectRaw('expense_categories.name as name, SUM(expenses.amount) as total')->get() as $e) {
            $a = Money::fromDbSum($e->total);
            $expenses += $a;
            $rows[] = ['line' => '  Expense: '.$e->name, 'amount' => -$a];
        }
        $rows[] = ['line' => 'Operating expenses', 'amount' => -$expenses];
        $rows[] = ['line' => 'NET PROFIT', 'amount' => $income - $cogs - $expenses];

        $rev = SalesData::revenueByStationKind($p);
        foreach (['kitchen' => 'Kitchen', 'bar' => 'Bar'] as $kind => $label) {
            $sales = $rev[$kind] ?? 0;
            $kCogs = SalesData::cogs($p, $kind);
            $kExp = SalesData::expenses($p, $kind);
            $rows[] = ['line' => $label.' P/L: sales − ingredients − '.$label.' expenses', 'amount' => $sales - $kCogs - $kExp];
        }

        return [
            'columns' => self::cols([['line', 'Line'], ['amount', 'Amount', 'money']]),
            'rows' => $rows,
            'totals' => null,
            'notes' => ['Investment / capex expenses and owner money are not counted in profit.', 'Kitchen and bar sales are item prices before bill discounts; ingredient cost is split by sales share.'],
        ];
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}
     */
    private static function audit(Period $p): array
    {
        $actions = ['order.item_voided', 'bill.discount', 'bill.returned', 'bill.cancelled', 'bill.reprinted', 'order.cancelled', 'expense.voided', 'approval.failed', 'dues.moved', 'dues.over_limit_approved', 'day.closed', 'stock.count', 'stock.adjustment', 'settings.updated', 'user.role_changed'];
        $rows = [];
        foreach (DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->whereIn('audit_logs.action', $actions)->whereBetween('audit_logs.created_at', $p->between())->orderByDesc('audit_logs.id')->limit(2000)
            ->get(['audit_logs.created_at', 'audit_logs.action', 'audit_logs.properties', 'users.name']) as $l) {
            $props = json_decode((string) ($l->properties ?? '{}'), true);
            $details = [];
            foreach (is_array($props) ? $props : [] as $k => $v) {
                if (is_scalar($v)) {
                    $details[] = $k.': '.$v;
                }
            }
            $rows[] = ['time' => substr((string) $l->created_at, 0, 16), 'user' => (string) ($l->name ?? '—'), 'action' => str_replace(['.', '_'], [' · ', ' '], (string) $l->action), 'details' => implode(', ', $details)];
        }

        return ['columns' => self::cols([['time', 'Time'], ['user', 'User'], ['action', 'Action'], ['details', 'Details']]), 'rows' => $rows, 'totals' => null, 'notes' => []];
    }

    /**
     * Payment-method labels for exports.
     *
     * @return array<string, string>
     */
    public static function methodLabels(): array
    {
        $out = [];
        foreach (PaymentMethod::cases() as $m) {
            $out[$m->value] = $m->label();
        }

        return $out;
    }
}
