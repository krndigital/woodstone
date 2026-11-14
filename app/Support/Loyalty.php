<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty points, tiers and visit history (FR-10.5 to FR-10.7). Points are earned on what was
 * paid (not on points used), worth a fixed rupee value when redeemed, and expire after a set
 * number of days. Expiry is written as its own ledger row the first time the balance is read.
 */
final class Loyalty
{
    public static function enabled(): bool
    {
        return Settings::bool('loyalty_enabled');
    }

    /** Paisa one point is worth. */
    public static function pointValue(): int
    {
        return Money::toPaisa(Settings::string('loyalty_point_value'));
    }

    public static function balance(Customer $customer, ?User $by = null): int
    {
        self::expire($customer, $by);

        return (int) LoyaltyEntry::query()->where('customer_id', $customer->id)->sum('points');
    }

    /**
     * Points earned for a paid amount: whole points per Rs 100.
     */
    public static function earnFor(int $paidPaisa): int
    {
        return intdiv($paidPaisa, 10000) * (int) Settings::get('loyalty_points_per_100');
    }

    public static function earn(Customer $customer, Bill $bill, int $paidPaisa, User $by): int
    {
        $points = self::earnFor($paidPaisa);
        if (! self::enabled() || $points <= 0) {
            return 0;
        }
        self::row($customer, $points, 'earn', $by, $bill->id, 'Bill '.$bill->number, now()->addDays((int) Settings::get('loyalty_expiry_days'))->toDateString());

        return $points;
    }

    public static function redeem(Customer $customer, int $points, Bill $bill, User $by): void
    {
        self::row($customer, -$points, 'redeem', $by, $bill->id, 'Used on bill '.$bill->number);
    }

    /**
     * Writes an "expire" row for earned points past their date that were not used (oldest first).
     */
    public static function expire(Customer $customer, ?User $by = null): void
    {
        $rows = LoyaltyEntry::query()->where('customer_id', $customer->id)->orderBy('id')->get();
        $used = 0;
        foreach ($rows as $r) {
            if ($r->points < 0) {
                $used -= $r->points;
            }
        }
        $expired = 0;
        foreach ($rows->where('points', '>', 0) as $earn) {
            $take = min($used, $earn->points);
            $used -= $take;
            $left = $earn->points - $take;
            if ($left > 0 && $earn->expires_on !== null && $earn->expires_on->lessThan(now()->startOfDay())) {
                $expired += $left;
            }
        }
        $actor = $by ?? User::query()->orderBy('id')->first();
        if ($expired > 0 && $actor !== null) {
            self::row($customer, -$expired, 'expire', $actor, null, 'Points expired');
        }
    }

    /**
     * @return array{visits: int, spent: int, last_visit: string|null, favourites: list<array{name: string, qty: int}>, spent_12m: int, tier: string}
     */
    public static function history(Customer $customer): array
    {
        $bills = DB::table('bills')->where('customer_id', $customer->id)->get(['id', 'total', 'created_at']);
        $spent = 0;
        $spent12 = 0;
        $last = null;
        $since = now()->subYear()->toDateTimeString();
        foreach ($bills as $b) {
            $p = Money::toPaisa((string) $b->total);
            $spent += $p;
            if ((string) $b->created_at >= $since) {
                $spent12 += $p;
            }
            $last = max($last ?? '', (string) $b->created_at);
        }
        $fav = array_values(DB::table('order_items')->join('bills', 'bills.order_id', '=', 'order_items.order_id')
            ->where('bills.customer_id', $customer->id)->where('order_items.status', '!=', 'void')
            ->groupBy('order_items.name')->selectRaw('order_items.name as name, SUM(order_items.quantity) as qty')
            ->orderByDesc('qty')->limit(3)->get()
            ->map(fn ($r): array => ['name' => (string) $r->name, 'qty' => (int) $r->qty])->all());

        return ['visits' => $bills->count(), 'spent' => $spent, 'last_visit' => $last, 'favourites' => $fav, 'spent_12m' => $spent12, 'tier' => self::tier($spent12)];
    }

    /**
     * Tier by spend in the last 12 months (FR-10.7).
     */
    public static function tier(int $spentPaisa): string
    {
        return match (true) {
            $spentPaisa >= Money::toPaisa(Settings::string('tier_gold_spend')) => 'Gold',
            $spentPaisa >= Money::toPaisa(Settings::string('tier_silver_spend')) => 'Silver',
            default => 'Regular',
        };
    }

    private static function row(Customer $customer, int $points, string $type, User $by, ?int $billId, ?string $note, ?string $expiresOn = null): void
    {
        $e = new LoyaltyEntry;
        $e->forceFill([
            'customer_id' => $customer->id, 'points' => $points, 'type' => $type, 'bill_id' => $billId,
            'expires_on' => $expiresOn, 'note' => $note, 'created_by' => $by->id, 'created_at' => now(),
        ])->save();
    }
}
