<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Stock\StockLedger;
use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Models\Bill;
use App\Models\LedgerEntry;
use App\Models\LoyaltyEntry;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Audit;
use App\Support\CashBook;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales return / bill cancel (FR-4.4, FR-4.8). The bill stays as it was; a return row records
 * the refund, money goes out of the account (or off the customer's due), and on a full cancel
 * the ingredients can go back into stock. Always needs a reason and Admin approval.
 */
final class ReturnSale
{
    public function __construct(private readonly StockLedger $ledger) {}

    public static function returnedPaisa(Bill $bill): int
    {
        $sum = 0;
        foreach (SalesReturn::query()->where('bill_id', $bill->id)->pluck('amount') as $a) {
            $sum += Money::toPaisa((string) $a);
        }

        return $sum;
    }

    public function handle(Bill $bill, ?string $amount, bool $fullCancel, PaymentMethod $method, bool $restock, string $reason, User $by, User $approver): SalesReturn
    {
        return DB::transaction(function () use ($bill, $amount, $fullCancel, $method, $restock, $reason, $by, $approver): SalesReturn {
            Bill::query()->whereKey($bill->id)->lockForUpdate()->first();
            $left = Money::toPaisa($bill->total) - self::returnedPaisa($bill);
            $paisa = $fullCancel ? $left : Money::toPaisa((string) $amount);

            if ($left <= 0) {
                throw ValidationException::withMessages(['amount' => __('This bill was already fully returned.')]);
            }
            if ($paisa <= 0 || $paisa > $left) {
                throw ValidationException::withMessages(['amount' => __('Return amount must be between 0.01 and :left.', ['left' => Money::fromPaisa($left)])]);
            }
            if ($restock && ! $fullCancel) {
                throw ValidationException::withMessages(['restock' => __('Stock can only be put back when the whole bill is cancelled.')]);
            }

            $return = new SalesReturn;
            $return->forceFill([
                'bill_id' => $bill->id, 'amount' => Money::fromPaisa($paisa), 'refund_method' => $method,
                'restock' => $restock, 'full_cancel' => $fullCancel, 'reason' => $reason,
                'created_by' => $by->id, 'approved_by' => $approver->id, 'created_at' => now(),
            ])->save();

            if ($method === PaymentMethod::Due) {
                $duePayment = $bill->payments()->where('method', PaymentMethod::Due->value)->first();
                if ($duePayment === null || $duePayment->customer_id === null) {
                    throw ValidationException::withMessages(['refund_method' => __('This bill had nothing on due.')]);
                }
                $entry = new LedgerEntry;
                $entry->forceFill([
                    'customer_id' => $duePayment->customer_id, 'type' => LedgerType::SalesReturn, 'amount' => Money::fromPaisa(-$paisa),
                    'bill_id' => $bill->id, 'note' => 'Return on bill '.$bill->number.': '.$reason, 'created_by' => $by->id, 'created_at' => now(),
                ])->save();
            } else {
                CashBook::post(CashBook::accountForMethod($method->value), -$paisa, 'sales_return', $return->id, $by, 'Refund bill '.$bill->number);
            }

            // A cancelled bill takes back the points it earned (FR-10.6).
            if ($fullCancel && $bill->customer_id !== null) {
                $earned = (int) LoyaltyEntry::query()->where('bill_id', $bill->id)->where('type', 'earn')->sum('points');
                if ($earned > 0) {
                    $back = new LoyaltyEntry;
                    $back->forceFill(['customer_id' => $bill->customer_id, 'points' => -$earned, 'type' => 'return', 'bill_id' => $bill->id, 'note' => 'Bill '.$bill->number.' cancelled', 'created_by' => $by->id, 'created_at' => now()])->save();
                }
            }

            if ($restock) {
                foreach (StockMovement::query()->with('item')->where('reference_type', 'bill')->where('reference_id', $bill->id)->get() as $m) {
                    $this->ledger->move($m->item, StockMovementType::Return, -Qty::toMilli($m->quantity), null, $by, 'Bill '.$bill->number.' cancelled', 'sales_return', $return->id);
                }
            }

            Audit::record($fullCancel ? 'bill.cancelled' : 'bill.returned', $bill, [
                'number' => $bill->number, 'amount' => $return->amount, 'method' => $method->value,
                'restock' => $restock, 'reason' => $reason, 'approved_by' => $approver->username,
            ], $by);

            return $return;
        });
    }
}
