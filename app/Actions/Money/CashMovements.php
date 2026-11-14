<?php

declare(strict_types=1);

namespace App\Actions\Money;

use App\Models\Account;
use App\Models\DayClose;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Audit;
use App\Support\CashBook;
use App\Support\Money;
use App\Support\SupplierBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual cash in/out, transfers, supplier payments and day open/close (FR-9.3, FR-9.4, FR-15.2, FR-4.6).
 */
final class CashMovements
{
    public function manual(Account $account, string $direction, string $amount, string $category, string $note, User $by): void
    {
        $paisa = $this->positive($amount);
        $signed = $direction === 'out' ? -$paisa : $paisa;
        $row = CashBook::post($account, $signed, 'manual', null, $by, $note, $category);
        Audit::record('cash.manual', $row, ['account' => $account->name, 'amount' => $row->amount, 'category' => $category, 'note' => $note], $by);
    }

    public function transfer(Account $from, Account $to, string $amount, ?string $note, User $by): void
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_account_id' => __('Choose a different account.')]);
        }
        $paisa = $this->positive($amount);

        DB::transaction(function () use ($from, $to, $paisa, $note, $by): void {
            $out = CashBook::post($from, -$paisa, 'transfer', null, $by, 'To '.$to->name.($note ? ' · '.$note : ''));
            CashBook::post($to, $paisa, 'transfer', $out->id, $by, 'From '.$from->name.($note ? ' · '.$note : ''));
            Audit::record('cash.transfer', $out, ['from' => $from->name, 'to' => $to->name, 'amount' => Money::fromPaisa($paisa)], $by);
        });
    }

    public function paySupplier(Supplier $supplier, string $amount, Account $account, string $paidOn, ?string $reference, ?string $note, User $by): SupplierPayment
    {
        $paisa = $this->positive($amount);

        return DB::transaction(function () use ($supplier, $paisa, $account, $paidOn, $reference, $note, $by): SupplierPayment {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->first();
            $owed = SupplierBalance::of($supplier->id);
            if ($paisa > $owed) {
                throw ValidationException::withMessages(['amount' => __('We owe :name only :owed.', ['name' => $supplier->name, 'owed' => Money::fromPaisa($owed)])]);
            }

            $payment = new SupplierPayment;
            $payment->forceFill([
                'supplier_id' => $supplier->id, 'amount' => Money::fromPaisa($paisa), 'account_id' => $account->id,
                'paid_on' => $paidOn, 'reference' => $reference, 'note' => $note, 'created_by' => $by->id, 'created_at' => now(),
            ])->save();

            CashBook::post($account, -$paisa, 'supplier_payment', $payment->id, $by, 'Paid '.$supplier->name, null, $paidOn);
            Audit::record('supplier.paid', $supplier, ['amount' => $payment->amount, 'account' => $account->name], $by);

            return $payment;
        });
    }

    public function openDay(string $openingCash, User $by): DayClose
    {
        if (DayClose::query()->whereNull('closed_at')->exists()) {
            throw ValidationException::withMessages(['opening_cash' => __('The day is already open. Close it first.')]);
        }
        $today = now()->toDateString();
        if (DayClose::query()->whereDate('business_date', $today)->exists()) {
            throw ValidationException::withMessages(['opening_cash' => __('Today was already opened and closed.')]);
        }

        $day = new DayClose;
        $day->forceFill([
            'business_date' => $today,
            'opening_cash' => Money::fromPaisa(Money::toPaisa($openingCash)),
            'opened_by' => $by->id,
            'opened_at' => now(),
        ])->save();
        Audit::record('day.opened', $day, ['opening_cash' => $day->opening_cash], $by);

        return $day;
    }

    /**
     * Expected cash = opening cash + cash in − cash out since the day was opened. A difference is
     * posted to the cash account as "cash over / short" so the book matches the drawer.
     */
    public function closeDay(string $counted, ?string $note, User $by): DayClose
    {
        return DB::transaction(function () use ($counted, $note, $by): DayClose {
            $day = DayClose::query()->whereNull('closed_at')->lockForUpdate()->first();
            if ($day === null) {
                throw ValidationException::withMessages(['counted_cash' => __('Open the day first.')]);
            }

            $cash = CashBook::account('cash');
            $expected = Money::toPaisa($day->opening_cash) + CashBook::balance($cash, null, $day->opened_at->toDateTimeString(), true);
            $countedPaisa = Money::toPaisa($counted);
            $difference = $countedPaisa - $expected;

            if ($difference !== 0) {
                CashBook::post($cash, $difference, 'day_close', $day->id, $by, $difference > 0 ? 'Cash over at day close' : 'Cash short at day close');
            }

            $day->forceFill([
                'expected_cash' => Money::fromPaisa($expected),
                'counted_cash' => Money::fromPaisa($countedPaisa),
                'difference' => Money::fromPaisa($difference),
                'note' => $note,
                'closed_by' => $by->id,
                'closed_at' => now(),
            ])->save();

            Audit::record('day.closed', $day, ['expected' => $day->expected_cash, 'counted' => $day->counted_cash, 'difference' => $day->difference], $by);

            return $day;
        });
    }

    private function positive(string $amount): int
    {
        $paisa = Money::toPaisa($amount);
        if ($paisa <= 0) {
            throw ValidationException::withMessages(['amount' => __('Amount must be more than zero.')]);
        }

        return $paisa;
    }
}
