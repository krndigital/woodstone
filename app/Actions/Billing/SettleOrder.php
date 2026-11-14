<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Pos\EnsureOrderOpen;
use App\Actions\Stock\DeductStockForOrder;
use App\Enums\LedgerType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\TableStatus;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\User;
use App\Support\Audit;
use App\Support\BillCalculator;
use App\Support\CashBook;
use App\Support\ManagerApproval;
use App\Support\Money;
use App\Support\NumberSequence;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes payment for an order and creates the final bill (step 7, FR-4, D-03, D-05, D-07).
 * Totals are recomputed here; the browser only sends the discount and how each person pays.
 */
final class SettleOrder
{
    public function __construct(private readonly DeductStockForOrder $deductStock) {}

    /**
     * @param  array{type: string|null, value: string, reason: string|null}  $discount
     * @param  list<array{payer: string|null, method: string, amount: string, customer_id: int|null, reference: string|null, qr_checked: bool}>  $payments
     * @param  array{username: string|null, secret: string|null}  $approval
     */
    public function handle(Order $order, User $cashier, array $discount, array $payments, array $approval): Bill
    {
        EnsureOrderOpen::check($order);

        return DB::transaction(function () use ($order, $cashier, $discount, $payments, $approval): Bill {
            // Lock the order so two cashiers can never bill it twice.
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            EnsureOrderOpen::check($order);

            if ($order->items()->where('status', OrderItemStatus::Draft->value)->exists()) {
                throw ValidationException::withMessages(['order' => __('Send or remove the items not yet sent to the kitchen first.')]);
            }
            if (! $order->items()->where('status', '!=', OrderItemStatus::Void->value)->exists()) {
                throw ValidationException::withMessages(['order' => __('There is nothing to bill. Cancel the order instead.')]);
            }

            $calc = BillCalculator::forOrder($order, $discount['type'], $discount['value']);
            $needsApproval = [];

            if ($calc['discount'] > 0) {
                if (! $cashier->hasPermission(Permission::GiveDiscount)) {
                    $needsApproval[] = 'discount';
                }
                if (trim((string) $discount['reason']) === '') {
                    throw ValidationException::withMessages(['discount_reason' => __('Write a reason for the discount.')]);
                }
                if ($calc['discount_bp'] > ((int) Settings::get('cashier_discount_limit_percent')) * 100) {
                    $needsApproval[] = 'discount_over_limit';
                }
            }

            // Payments must add up to the bill exactly.
            $sum = 0;
            $duePerCustomer = [];
            foreach ($payments as $i => $p) {
                $method = PaymentMethod::from($p['method']);
                $amount = Money::toPaisa($p['amount']);
                $sum += $amount;

                if (! $method->isEnabled()) {
                    throw ValidationException::withMessages(["payments.$i.method" => __(':method payments are turned off in settings.', ['method' => $method->label()])]);
                }
                if ($amount <= 0) {
                    throw ValidationException::withMessages(["payments.$i.amount" => __('Each payment must be more than zero.')]);
                }
                if ($method === PaymentMethod::Qr && ! $p['qr_checked']) {
                    throw ValidationException::withMessages(["payments.$i.qr_checked" => __('Check the QR payment in the merchant app and tick "I have checked".')]);
                }
                if ($method === PaymentMethod::Due) {
                    if (! $cashier->hasPermission(Permission::ManageDues)) {
                        throw ValidationException::withMessages(["payments.$i.method" => __('You cannot put bills on due.')]);
                    }
                    if ($p['customer_id'] === null) {
                        throw ValidationException::withMessages(["payments.$i.customer_id" => __('Choose the customer for the due.')]);
                    }
                    $duePerCustomer[$p['customer_id']] = ($duePerCustomer[$p['customer_id']] ?? 0) + $amount;
                }
            }

            if ($sum !== $calc['total']) {
                throw ValidationException::withMessages(['payments' => __('Payments add up to :paid but the bill is :total.', ['paid' => Money::fromPaisa($sum), 'total' => Money::fromPaisa($calc['total'])])]);
            }

            $customers = [];
            foreach ($duePerCustomer as $customerId => $due) {
                $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->first();
                if ($customer === null || ! $customer->is_active) {
                    throw ValidationException::withMessages(['payments' => __('That customer account is turned off.')]);
                }
                if ($customer->balancePaisa() + $due > Money::toPaisa($customer->credit_limit)) {
                    $needsApproval[] = 'credit_limit:'.$customer->id;
                }
                $customers[$customerId] = $customer;
            }

            $approver = $needsApproval === [] ? null : ManagerApproval::resolve($cashier, $approval['username'], $approval['secret']);

            $paid = $sum - array_sum($duePerCustomer);
            $bill = new Bill;
            $bill->forceFill([
                'number' => NumberSequence::nextBillNumber(),
                'order_id' => $order->id,
                'subtotal' => Money::fromPaisa($calc['subtotal']),
                'discount_type' => $calc['discount'] > 0 ? $discount['type'] : null,
                'discount_value' => $calc['discount'] > 0 ? Money::fromPaisa(Money::toPaisa($discount['value'])) : '0.00',
                'discount_amount' => Money::fromPaisa($calc['discount']),
                'discount_reason' => $calc['discount'] > 0 ? trim((string) $discount['reason']) : null,
                'discount_approved_by' => $calc['discount'] > 0 ? ($approver ?? $cashier)->id : null,
                'service_percent' => $calc['service_percent'],
                'service_amount' => Money::fromPaisa($calc['service']),
                'vat_percent' => $calc['vat_percent'],
                'vat_amount' => Money::fromPaisa($calc['vat']),
                'round_off' => Money::fromPaisa($calc['round_off']),
                'total' => Money::fromPaisa($calc['total']),
                'paid_amount' => Money::fromPaisa($paid),
                'due_amount' => Money::fromPaisa(array_sum($duePerCustomer)),
                'cashier_id' => $cashier->id,
                'print_count' => 0,
            ])->save();

            foreach ($payments as $p) {
                $method = PaymentMethod::from($p['method']);
                $payment = new BillPayment;
                $payment->forceFill([
                    'bill_id' => $bill->id,
                    'payer' => $p['payer'],
                    'method' => $method,
                    'amount' => Money::fromPaisa(Money::toPaisa($p['amount'])),
                    'customer_id' => $method === PaymentMethod::Due ? $p['customer_id'] : null,
                    'reference' => $p['reference'],
                    'qr_checked' => $method === PaymentMethod::Qr && $p['qr_checked'],
                    'received_by' => $cashier->id,
                    'created_at' => now(),
                ])->save();

                if ($method !== PaymentMethod::Due) {
                    CashBook::post(CashBook::accountForMethod($method->value), Money::toPaisa($p['amount']), 'bill_payment', $payment->id, $cashier, 'Bill '.$bill->number);
                }
            }

            foreach ($duePerCustomer as $customerId => $due) {
                $entry = new LedgerEntry;
                $entry->forceFill([
                    'customer_id' => $customerId,
                    'type' => LedgerType::BillDue,
                    'amount' => Money::fromPaisa($due),
                    'bill_id' => $bill->id,
                    'note' => 'Bill '.$bill->number,
                    'created_by' => $cashier->id,
                    'created_at' => now(),
                ])->save();
            }

            $this->deductStock->handle($order, $bill, $cashier);

            $order->forceFill(['status' => OrderStatus::Paid, 'closed_at' => now()])->save();
            $order->diningTable?->setStatus(TableStatus::Cleaning);

            Audit::record('bill.settled', $bill, [
                'number' => $bill->number, 'order' => $order->number, 'total' => $bill->total,
                'paid' => $bill->paid_amount, 'due' => $bill->due_amount,
                'methods' => array_values(array_unique(array_map(fn (array $p): string => $p['method'], $payments))),
            ], $cashier);

            if ($calc['discount'] > 0) {
                Audit::record('bill.discount', $bill, [
                    'number' => $bill->number, 'type' => $bill->discount_type, 'value' => $bill->discount_value,
                    'amount' => $bill->discount_amount, 'reason' => $bill->discount_reason,
                    'approved_by' => ($approver ?? $cashier)->username,
                ], $cashier);
            }

            foreach ($needsApproval as $reason) {
                if (str_starts_with($reason, 'credit_limit:')) {
                    $customer = $customers[(int) substr($reason, 13)];
                    Audit::record('dues.over_limit_approved', $customer, ['bill' => $bill->number, 'approved_by' => $approver?->username], $cashier);
                }
            }

            return $bill;
        });
    }
}
