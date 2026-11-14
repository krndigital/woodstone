<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use App\Models\LedgerEntry;
use Carbon\CarbonInterface;

/**
 * Customer balances and ageing (FR-10.3). Payments are matched to the oldest dues first.
 */
final class DuesData
{
    /**
     * @return array{balance: int, age_0_30: int, age_31_60: int, age_60_plus: int, oldest: string|null}
     */
    public static function summary(Customer $customer, ?CarbonInterface $today = null): array
    {
        $today ??= now();
        /** @var list<array{amount: int, at: CarbonInterface}> $open */
        $open = [];
        $credit = 0;

        foreach ($customer->ledger()->orderBy('id')->get(['amount', 'created_at']) as $entry) {
            /** @var LedgerEntry $entry */
            $amount = Money::toPaisa($entry->amount);
            if ($amount > 0) {
                $open[] = ['amount' => $amount, 'at' => $entry->created_at];
            } else {
                $credit += -$amount;
            }
        }

        // Use up credits against the oldest dues.
        foreach ($open as $i => $due) {
            $used = min($credit, $due['amount']);
            $open[$i]['amount'] -= $used;
            $credit -= $used;
        }

        $buckets = ['age_0_30' => 0, 'age_31_60' => 0, 'age_60_plus' => 0];
        $oldest = null;
        foreach ($open as $due) {
            if ($due['amount'] <= 0) {
                continue;
            }
            $oldest ??= $due['at']->toIso8601String();
            $days = (int) $due['at']->diffInDays($today, true);
            $key = $days <= 30 ? 'age_0_30' : ($days <= 60 ? 'age_31_60' : 'age_60_plus');
            $buckets[$key] += $due['amount'];
        }

        return ['balance' => $buckets['age_0_30'] + $buckets['age_31_60'] + $buckets['age_60_plus'] - $credit, ...$buckets, 'oldest' => $oldest];
    }

    /**
     * Active customers for the billing screen.
     *
     * @return list<array{id: int, name: string, phone: string|null, balance: string, credit_limit: string}>
     */
    public static function pickList(): array
    {
        $list = [];
        foreach (Customer::query()->where('is_active', true)->orderBy('name')->get() as $c) {
            $list[] = [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'balance' => Money::fromPaisa($c->balancePaisa()),
                'credit_limit' => $c->credit_limit,
            ];
        }

        return $list;
    }
}
