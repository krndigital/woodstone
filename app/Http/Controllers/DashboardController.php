<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\User;
use App\Reports\Period;
use App\Reports\SalesData;
use App\Support\CashBook;
use App\Support\Money;
use App\Support\OrderData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner dashboard (step 12, FR-11, §6): today's or this month's key figures on one screen,
 * running tables, top items and the sales trend. Other roles get the simple welcome screen.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasPermission(Permission::ViewDashboard)) {
            return Inertia::render('Dashboard', ['owner' => null]);
        }

        $range = $request->query('range') === 'month' ? 'month' : 'today';
        $today = CarbonImmutable::today();
        $period = $range === 'month' ? Period::make($today->startOfMonth()->toDateString(), $today->toDateString()) : Period::make($today->toDateString(), $today->toDateString());

        $t = SalesData::totals($period);
        $cogs = SalesData::cogs($period);
        $expenses = SalesData::expenses($period);

        // Trend: last 14 days, or each day of this month.
        $start = $range === 'month' ? $today->startOfMonth() : $today->subDays(13);
        $trend = [];
        for ($d = $start; $d->lessThanOrEqualTo($today); $d = $d->addDay()) {
            $trend[$d->toDateString()] = 0;
        }
        foreach (DB::table('bills')->whereBetween('created_at', [$start->startOfDay()->toDateTimeString(), $today->endOfDay()->toDateTimeString()])->get(['created_at', 'total']) as $b) {
            $key = substr((string) $b->created_at, 0, 10);
            if (array_key_exists($key, $trend)) {
                $trend[$key] += Money::toPaisa((string) $b->total);
            }
        }

        $mix = [];
        foreach (DB::table('bill_payments')->join('bills', 'bills.id', '=', 'bill_payments.bill_id')->whereBetween('bills.created_at', $period->between())->groupBy('bill_payments.method')->selectRaw('bill_payments.method as method, SUM(bill_payments.amount) as total')->get() as $m) {
            $mix[(string) $m->method] = Money::fromDbSum($m->total);
        }

        return Inertia::render('Dashboard', [
            'owner' => [
                'range' => $range,
                'kpis' => [
                    'sales' => $t['net'],
                    'bills' => $t['bills'],
                    'average' => $t['bills'] > 0 ? intdiv($t['total'], $t['bills']) : 0,
                    'covers' => $t['covers'],
                    'discount' => $t['discount'],
                    'due' => $t['due'],
                    'cash' => CashBook::balance(CashBook::account('cash')),
                    'expenses' => $expenses,
                    'profit' => $t['net'] - $t['vat'] - $cogs - $expenses,
                ],
                'mix' => $mix,
                'topItems' => SalesData::topItems($period),
                'trend' => array_map(fn (string $date, int $paisa): array => ['date' => $date, 'sales' => $paisa], array_keys($trend), array_values($trend)),
                'running' => OrderData::openOrders(),
                'openCount' => DB::table('orders')->where('status', OrderStatus::Open->value)->count(),
            ],
        ]);
    }
}
