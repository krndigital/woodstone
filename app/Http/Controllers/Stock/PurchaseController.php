<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\RecordPurchase;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\PurchaseRequest;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchase entry and goods received against a PO (step 9, FR-6.2/6.4/6.6).
 */
class PurchaseController extends Controller
{
    public function index(): Response
    {
        $purchases = Purchase::query()->with(['supplier:id,name', 'creator:id,name', 'lines.stockItem:id,name,unit,purchase_unit'])->latest('id')->limit(100)->get()
            ->map(fn (Purchase $p): array => [
                'id' => $p->id,
                'number' => $p->number,
                'supplier' => $p->supplier->name,
                'supplier_bill_no' => $p->supplier_bill_no,
                'purchased_on' => $p->purchased_on->toDateString(),
                'total' => $p->total,
                'paid_amount' => $p->paid_amount,
                'paid_method' => $p->paid_method,
                'by' => $p->creator->name,
                'lines' => $p->lines->map(fn (PurchaseLine $l): array => [
                    'name' => $l->stockItem->name,
                    'quantity' => $l->quantity,
                    'unit' => $l->in_purchase_unit ? $l->stockItem->purchase_unit : $l->stockItem->unit,
                    'rate' => $l->rate,
                    'line_total' => $l->line_total,
                ])->values()->all(),
            ])->values()->all();

        return Inertia::render('stock/Purchases', [
            'purchases' => $purchases,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->toArray(),
            'items' => StockItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit', 'purchase_unit', 'purchase_factor', 'avg_cost'])->toArray(),
            'openOrders' => PurchaseOrder::query()->with(['supplier:id,name', 'lines'])
                ->whereIn('status', [PurchaseOrderStatus::Draft->value, PurchaseOrderStatus::Sent->value, PurchaseOrderStatus::Partial->value])
                ->orderBy('id')->get()
                ->map(fn (PurchaseOrder $o): array => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'supplier_id' => $o->supplier_id,
                    'supplier' => $o->supplier->name,
                    'lines' => $o->lines->map(fn (PurchaseOrderLine $l): array => [
                        'id' => $l->id, 'stock_item_id' => $l->stock_item_id, 'quantity' => $l->quantity,
                        'received_quantity' => $l->received_quantity, 'expected_rate' => $l->expected_rate,
                    ])->values()->all(),
                ])->values()->all(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(PurchaseRequest $request, RecordPurchase $action): RedirectResponse
    {
        $result = $action->handle(
            Supplier::query()->findOrFail($request->integer('supplier_id')),
            $request->lines(),
            [
                'purchased_on' => $request->string('purchased_on')->toString(),
                'supplier_bill_no' => $request->filled('supplier_bill_no') ? $request->string('supplier_bill_no')->trim()->toString() : null,
                'paid_amount' => $request->filled('paid_amount') ? $request->string('paid_amount')->toString() : '0',
                'paid_method' => $request->filled('paid_method') ? $request->string('paid_method')->toString() : null,
                'note' => $request->filled('note') ? $request->string('note')->trim()->toString() : null,
                'due_on' => $request->filled('due_on') ? $request->string('due_on')->toString() : null,
            ],
            $request->actor(),
            $request->filled('purchase_order_id') ? PurchaseOrder::query()->findOrFail($request->integer('purchase_order_id')) : null,
        );

        $message = __('Purchase :number saved. Stock updated.', ['number' => $result['purchase']->number]);
        Inertia::flash('toast', $result['differences'] === []
            ? ['type' => 'success', 'message' => $message]
            : ['type' => 'warning', 'message' => $message.' '.__('Differences: ').implode(' ', $result['differences'])]);

        return back();
    }
}
