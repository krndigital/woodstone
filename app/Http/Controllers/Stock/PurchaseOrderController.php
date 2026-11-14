<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\PurchaseOrderRequest;
use App\Http\Requests\Stock\PurchaseOrderStatusRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Support\Audit;
use App\Support\Money;
use App\Support\NumberSequence;
use App\Support\Qty;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchase orders (FR-6.5): draft → sent → partly received → received, or cancelled.
 */
class PurchaseOrderController extends Controller
{
    public function index(): Response
    {
        $orders = PurchaseOrder::query()->with(['supplier:id,name', 'lines.stockItem:id,name,unit,purchase_unit'])->latest('id')->limit(100)->get()
            ->map(function (PurchaseOrder $o): array {
                $total = 0;
                $lines = [];
                foreach ($o->lines as $l) {
                    $total += Qty::div(Money::toPaisa($l->expected_rate) * Qty::toMilli($l->quantity), 1000);
                    $lines[] = [
                        'name' => $l->stockItem->name,
                        'unit' => $l->stockItem->purchase_unit ?? $l->stockItem->unit,
                        'quantity' => $l->quantity,
                        'received_quantity' => $l->received_quantity,
                        'expected_rate' => $l->expected_rate,
                    ];
                }

                return [
                    'id' => $o->id,
                    'number' => $o->number,
                    'supplier' => $o->supplier->name,
                    'status' => $o->status->value,
                    'status_label' => $o->status->label(),
                    'expected_on' => $o->expected_on?->toDateString(),
                    'note' => $o->note,
                    'total' => Money::fromPaisa($total),
                    'lines' => $lines,
                ];
            })->values()->all();

        return Inertia::render('stock/Orders', [
            'orders' => $orders,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->toArray(),
            'items' => StockItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit', 'purchase_unit', 'avg_cost', 'purchase_factor'])->toArray(),
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $order = DB::transaction(function () use ($request): PurchaseOrder {
            $order = new PurchaseOrder;
            $order->forceFill([
                'number' => 'PO-'.str_pad((string) NumberSequence::next('purchase_order'), 5, '0', STR_PAD_LEFT),
                'supplier_id' => $request->integer('supplier_id'),
                'status' => PurchaseOrderStatus::Draft,
                'expected_on' => $request->filled('expected_on') ? $request->string('expected_on')->toString() : null,
                'note' => $request->filled('note') ? $request->string('note')->trim()->toString() : null,
                'created_by' => $request->actor()->id,
            ])->save();

            foreach ((array) $request->validated('lines') as $l) {
                $l = (array) $l;
                $line = new PurchaseOrderLine;
                $line->forceFill([
                    'purchase_order_id' => $order->id,
                    'stock_item_id' => (int) $l['stock_item_id'],
                    'quantity' => Qty::fromMilli(Qty::toMilli((string) $l['quantity'])),
                    'expected_rate' => Money::fromPaisa(Money::toPaisa((string) $l['expected_rate'])),
                    'received_quantity' => '0',
                ])->save();
            }

            return $order;
        });

        Audit::record('stock.po_created', $order, ['number' => $order->number]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase order :number created.', ['number' => $order->number])]);

        return back();
    }

    public function updateStatus(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $target = PurchaseOrderStatus::from($request->string('status')->toString());
        $allowed = match ($target) {
            PurchaseOrderStatus::Sent => $purchaseOrder->status === PurchaseOrderStatus::Draft,
            PurchaseOrderStatus::Cancelled => in_array($purchaseOrder->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent], true),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages(['status' => __('A :status order cannot be changed to :target.', ['status' => mb_strtolower($purchaseOrder->status->label()), 'target' => mb_strtolower($target->label())])]);
        }

        $from = $purchaseOrder->status;
        $purchaseOrder->forceFill(['status' => $target])->save();
        Audit::record('stock.po_status', $purchaseOrder, ['number' => $purchaseOrder->number, 'from' => $from->value, 'to' => $target->value]);

        return back();
    }

    /**
     * Printable PO to hand over or photograph for WhatsApp.
     */
    public function print(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'lines.stockItem']);

        return view('print.purchase-order', ['po' => $purchaseOrder, 'settings' => Settings::all()]);
    }
}
