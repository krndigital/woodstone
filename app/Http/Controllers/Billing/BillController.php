<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ReturnSale;
use App\Actions\Billing\SettleOrder;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ReturnSaleRequest;
use App\Http\Requests\Billing\SettleRequest;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Audit;
use App\Support\DuesData;
use App\Support\ManagerApproval;
use App\Support\Money;
use App\Support\OrderData;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Billing (step 7). Every route is behind `can:billing.take_payment`.
 */
class BillController extends Controller
{
    public function index(Request $request): Response
    {
        $bills = Bill::query()
            ->with(['order.diningTable', 'cashier:id,name', 'payments'])
            ->withSum('returns as returned_sum', 'amount')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Bill $b): array => [
                'id' => $b->id,
                'number' => $b->number,
                'label' => $b->order->label(),
                'total' => $b->total,
                'paid' => $b->paid_amount,
                'due' => $b->due_amount,
                'discount' => $b->discount_amount,
                'methods' => $b->payments->map(fn (BillPayment $p): string => $p->method->label())->unique()->values()->all(),
                'cashier' => $b->cashier->name,
                'print_count' => $b->print_count,
                'returned' => Money::fromPaisa(Money::fromDbSum($b->getAttribute('returned_sum'))),
                'has_due' => $b->payments->contains(fn (BillPayment $p): bool => $p->method === PaymentMethod::Due),
                'created_at' => $b->created_at?->toIso8601String(),
            ])
            ->values()->all();

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('billing/Index', [
            'bills' => $bills,
            'methods' => array_map(fn (PaymentMethod $m): array => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'can' => ['approve' => $user->hasPermission(Permission::ApproveOverride)],
        ]);
    }

    /**
     * Sales return or full bill cancel with reason and Admin approval (FR-4.4, FR-4.8).
     */
    public function returnSale(ReturnSaleRequest $request, Bill $bill, ReturnSale $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $approver = ManagerApproval::resolve(
            $user,
            $request->filled('approver_username') ? $request->string('approver_username')->toString() : null,
            $request->filled('approver_secret') ? $request->string('approver_secret')->toString() : null,
        );

        $return = $action->handle(
            $bill,
            $request->filled('amount') ? $request->string('amount')->toString() : null,
            $request->boolean('full_cancel'),
            PaymentMethod::from($request->string('refund_method')->toString()),
            $request->boolean('restock'),
            $request->string('reason')->trim()->toString(),
            $user,
            $approver,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Return of Rs :amount on bill :number saved.', ['amount' => $return->amount, 'number' => $bill->number])]);

        return back();
    }

    public function checkout(Request $request, Order $order): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $order->isOpen()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Order #:number is closed.', ['number' => $order->number])]);

            return to_route('pos.index');
        }

        $vatable = 0;
        foreach ($order->items()->with('menuItem:id,vat_applicable')->where('status', '!=', OrderItemStatus::Void->value)->get() as $line) {
            if ($line->menuItem === null || $line->menuItem->vat_applicable) {
                $vatable += Money::toPaisa($line->line_total);
            }
        }

        return Inertia::render('billing/Checkout', [
            'order' => OrderData::order($order),
            'vatable' => Money::fromPaisa($vatable),
            'minSpend' => $order->diningTable !== null ? $order->diningTable->min_spend : '0.00',
            'advanceLeft' => ($r = Reservation::query()->where('order_id', $order->id)->first()) !== null ? Money::fromPaisa(Money::toPaisa($r->advance) - Money::toPaisa($r->advance_used)) : '0.00',
            'settings' => [
                'service_percent' => Settings::string('service_charge_percent'),
                'vat_percent' => Settings::string('vat_percent'),
                'discount_limit' => (int) Settings::get('cashier_discount_limit_percent'),
                'methods' => array_values(array_map(fn (PaymentMethod $m): array => ['value' => $m->value, 'label' => $m->label()], array_filter(PaymentMethod::cases(), fn (PaymentMethod $m): bool => $m->isEnabled()))),
                'auto_print' => Settings::bool('auto_print'),
                'round_off' => Settings::bool('round_off'),
            ],
            'customers' => $user->hasPermission(Permission::ManageDues) ? DuesData::pickList() : [],
            'can' => [
                'discount' => $user->hasPermission(Permission::GiveDiscount),
                'approve' => $user->hasPermission(Permission::ApproveOverride),
                'due' => $user->hasPermission(Permission::ManageDues),
            ],
        ]);
    }

    public function settle(SettleRequest $request, Order $order, SettleOrder $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bill = $action->handle(
            $order,
            $user,
            [
                'type' => $request->filled('discount_type') ? $request->string('discount_type')->toString() : null,
                'value' => $request->filled('discount_value') ? $request->string('discount_value')->toString() : '0',
                'reason' => $request->filled('discount_reason') ? $request->string('discount_reason')->toString() : null,
            ],
            $request->payments(),
            [
                'username' => $request->filled('approver_username') ? $request->string('approver_username')->toString() : null,
                'secret' => $request->filled('approver_secret') ? $request->string('approver_secret')->toString() : null,
            ],
            $request->filled('customer_id') ? $request->integer('customer_id') : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill :number paid.', ['number' => $bill->number])]);
        if (Settings::bool('auto_print')) {
            Inertia::flash('printBill', $bill->id);
        }

        return to_route('pos.index');
    }

    /**
     * 80 mm bill. First print is the original; later prints say COPY (FR-4.3) and are audited.
     */
    public function print(Request $request, Bill $bill): View
    {
        $bill->load(['order.diningTable', 'order.waiter:id,name', 'order.items', 'cashier:id,name', 'payments.customer', 'customer']);
        $copy = $bill->print_count > 0;
        $bill->forceFill(['print_count' => $bill->print_count + 1])->save();

        if ($copy) {
            Audit::record('bill.reprinted', $bill, ['number' => $bill->number, 'print_count' => $bill->print_count]);
        }

        // Due account block (D-07): balance before and after this bill, per customer.
        $dueAccounts = [];
        foreach (LedgerEntry::query()->with('bill')->where('bill_id', $bill->id)->get() as $entry) {
            $after = 0;
            foreach (LedgerEntry::query()->where('customer_id', $entry->customer_id)->where('id', '<=', $entry->id)->pluck('amount') as $amount) {
                $after += Money::toPaisa((string) $amount);
            }
            $customer = $bill->payments->firstWhere('customer_id', $entry->customer_id)?->customer;
            $dueAccounts[] = [
                'name' => $customer !== null ? $customer->name : '—',
                'phone' => $customer?->phone,
                'before' => Money::fromPaisa($after - Money::toPaisa($entry->amount)),
                'this_bill' => $entry->amount,
                'after' => Money::fromPaisa($after),
            ];
        }

        return view('print.bill', [
            'bill' => $bill,
            'copy' => $copy,
            'dueAccounts' => $dueAccounts,
            'settings' => Settings::all(),
        ]);
    }
}
