<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\NumberSequence;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase entry / goods received (FR-6.2, FR-6.4, FR-6.6). Totals are computed here from
 * quantity × rate; each line adds stock and updates the average cost.
 */
final class RecordPurchase
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  list<array{stock_item_id: int, quantity: string, in_purchase_unit: bool, rate: string, po_line_id: int|null}>  $lines
     * @param  array{purchased_on: string, supplier_bill_no: string|null, paid_amount: string, paid_method: string|null, note: string|null}  $details
     * @return array{purchase: Purchase, differences: list<string>}
     */
    public function handle(Supplier $supplier, array $lines, array $details, User $by, ?PurchaseOrder $po = null): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('Add at least one item.')]);
        }
        if ($po !== null && ($po->supplier_id !== $supplier->id || ! $po->status->canReceive())) {
            throw ValidationException::withMessages(['purchase_order_id' => __('This purchase order cannot be received.')]);
        }

        return DB::transaction(function () use ($supplier, $lines, $details, $by, $po): array {
            $items = StockItem::query()->whereIn('id', array_column($lines, 'stock_item_id'))->get()->keyBy('id');
            $poLines = PurchaseOrderLine::query()->where('purchase_order_id', $po === null ? 0 : $po->id)->lockForUpdate()->get()->keyBy('id');

            $prepared = [];
            $total = 0;
            $differences = [];
            foreach ($lines as $i => $line) {
                $item = $items->get($line['stock_item_id']);
                if ($item === null || ! $item->is_active) {
                    throw ValidationException::withMessages(["lines.$i.stock_item_id" => __('Choose an active stock item.')]);
                }
                $qty = Qty::toMilli($line['quantity']);
                if ($qty <= 0) {
                    throw ValidationException::withMessages(["lines.$i.quantity" => __('Quantity must be more than zero.')]);
                }
                $usePurchaseUnit = $line['in_purchase_unit'] && $item->purchase_factor !== null;
                $base = $usePurchaseUnit ? Qty::div($qty * Qty::toMilli($item->purchase_factor), 1000) : $qty;
                $ratePaisa = Money::toPaisa($line['rate']);
                $linePaisa = Qty::div($ratePaisa * $qty, 1000);
                // cost per base unit in 1/10000 rupee: paisa × 100 / base units
                $unitCost = Qty::div($linePaisa * 100 * 1000, $base);
                $total += $linePaisa;

                $poLine = $line['po_line_id'] === null ? null : $poLines->get($line['po_line_id']);
                if ($line['po_line_id'] !== null && ($poLine === null || $poLine->stock_item_id !== $item->id)) {
                    throw ValidationException::withMessages(["lines.$i.po_line_id" => __('That line is not on this purchase order.')]);
                }
                if ($poLine !== null) {
                    $left = Qty::toMilli($poLine->quantity) - Qty::toMilli($poLine->received_quantity);
                    if ($qty !== $left) {
                        $differences[] = __(':item: ordered :ordered, received :got.', ['item' => $item->name, 'ordered' => Qty::fromMilli($left), 'got' => Qty::fromMilli($qty)]);
                    }
                    if ($ratePaisa !== Money::toPaisa($poLine->expected_rate)) {
                        $differences[] = __(':item: expected rate :expected, bill rate :rate.', ['item' => $item->name, 'expected' => $poLine->expected_rate, 'rate' => Money::fromPaisa($ratePaisa)]);
                    }
                }

                $prepared[] = compact('item', 'qty', 'usePurchaseUnit', 'base', 'ratePaisa', 'linePaisa', 'unitCost', 'poLine');
            }

            $paid = Money::toPaisa($details['paid_amount'] === '' ? '0' : $details['paid_amount']);
            if ($paid > $total) {
                throw ValidationException::withMessages(['paid_amount' => __('Paid now cannot be more than the purchase total.')]);
            }
            if ($paid > 0 && $details['paid_method'] === null) {
                throw ValidationException::withMessages(['paid_method' => __('How was it paid?')]);
            }

            $purchase = new Purchase;
            $purchase->forceFill([
                'number' => 'P-'.str_pad((string) NumberSequence::next('purchase'), 5, '0', STR_PAD_LEFT),
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $po?->id,
                'supplier_bill_no' => $details['supplier_bill_no'],
                'purchased_on' => $details['purchased_on'],
                'total' => Money::fromPaisa($total),
                'paid_amount' => Money::fromPaisa($paid),
                'paid_method' => $paid > 0 ? $details['paid_method'] : null,
                'note' => $details['note'],
                'created_by' => $by->id,
            ])->save();

            foreach ($prepared as $p) {
                $row = new PurchaseLine;
                $row->forceFill([
                    'purchase_id' => $purchase->id,
                    'stock_item_id' => $p['item']->id,
                    'quantity' => Qty::fromMilli($p['qty']),
                    'in_purchase_unit' => $p['usePurchaseUnit'],
                    'base_quantity' => Qty::fromMilli($p['base']),
                    'rate' => Money::fromPaisa($p['ratePaisa']),
                    'line_total' => Money::fromPaisa($p['linePaisa']),
                ])->save();

                $this->ledger->move($p['item'], StockMovementType::Purchase, $p['base'], $p['unitCost'], $by, null, 'purchase', $purchase->id);

                if ($p['poLine'] !== null) {
                    $p['poLine']->forceFill(['received_quantity' => Qty::fromMilli(Qty::toMilli($p['poLine']->received_quantity) + $p['qty'])])->save();
                }
            }

            if ($po !== null) {
                $open = PurchaseOrderLine::query()->where('purchase_order_id', $po->id)->get()
                    ->contains(fn (PurchaseOrderLine $l): bool => Qty::toMilli($l->received_quantity) < Qty::toMilli($l->quantity));
                $po->forceFill(['status' => $open ? PurchaseOrderStatus::Partial : PurchaseOrderStatus::Received])->save();
            }

            Audit::record('stock.purchase_recorded', $purchase, [
                'number' => $purchase->number, 'supplier' => $supplier->name, 'total' => $purchase->total,
                'paid' => $purchase->paid_amount, 'po' => $po?->number, 'differences' => $differences,
            ], $by);

            return ['purchase' => $purchase, 'differences' => $differences];
        });
    }
}
