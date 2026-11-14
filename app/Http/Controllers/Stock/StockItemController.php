<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\AdjustStock;
use App\Enums\StockKind;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AdjustStockRequest;
use App\Http\Requests\Stock\StockItemRequest;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock items, levels and hand entries (step 9, FR-7). Behind `can:stock.manage`.
 */
class StockItemController extends Controller
{
    public function index(Request $request): Response
    {
        $itemId = $request->integer('item') ?: null;

        $items = StockItem::query()->orderBy('name')->get()->map(function (StockItem $i): array {
            $onHand = Qty::toMilli($i->on_hand);
            $value = Qty::div($onHand * Qty::toCost($i->avg_cost), 1000 * 100); // paisa

            return [
                'id' => $i->id,
                'name' => $i->name,
                'kind' => $i->kind->value,
                'kind_label' => $i->kind->label(),
                'unit' => $i->unit,
                'purchase_unit' => $i->purchase_unit,
                'purchase_factor' => $i->purchase_factor,
                'on_hand' => $i->on_hand,
                'avg_cost' => $i->avg_cost,
                'value' => Money::fromPaisa($value),
                'reorder_level' => $i->reorder_level,
                'low' => $i->is_active && $onHand <= Qty::toMilli($i->reorder_level),
                'is_active' => $i->is_active,
            ];
        })->values()->all();

        return Inertia::render('stock/Items', [
            'items' => $items,
            'kinds' => array_map(fn (StockKind $k): array => ['value' => $k->value, 'label' => $k->label()], StockKind::cases()),
            'entryTypes' => array_map(fn (StockMovementType $t): array => ['value' => $t->value, 'label' => $t->label()], StockMovementType::manual()),
            'movements' => Inertia::optional(fn (): array => $itemId === null ? [] : $this->movements($itemId)),
        ]);
    }

    public function store(StockItemRequest $request): RedirectResponse
    {
        $item = new StockItem($this->details($request));
        $item->forceFill(['on_hand' => '0', 'avg_cost' => '0', 'is_active' => true])->save();
        Audit::record('stock.item_created', $item, ['name' => $item->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $item->name])]);

        return back();
    }

    public function update(StockItemRequest $request, StockItem $stockItem): RedirectResponse
    {
        $stockItem->fill($this->details($request));
        $stockItem->forceFill(['is_active' => $request->boolean('is_active', true)])->save();
        Audit::record('stock.item_updated', $stockItem, ['name' => $stockItem->name, 'is_active' => $stockItem->is_active]);

        return back();
    }

    public function adjust(AdjustStockRequest $request, StockItem $stockItem, AdjustStock $action): RedirectResponse
    {
        $action->handle(
            $stockItem,
            StockMovementType::from($request->string('type')->toString()),
            $request->string('quantity')->toString(),
            $request->filled('unit_cost') ? $request->string('unit_cost')->toString() : null,
            $request->string('reason')->trim()->toString(),
            $request->actor(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now :qty :unit.', ['name' => $stockItem->name, 'qty' => rtrim(rtrim($stockItem->on_hand, '0'), '.'), 'unit' => $stockItem->unit])]);

        return back();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movements(int $itemId): array
    {
        $out = [];
        foreach (StockMovement::query()->with('creator:id,name')->where('stock_item_id', $itemId)->latest('id')->limit(100)->get() as $m) {
            $out[] = [
                'id' => $m->id,
                'type' => $m->type->label(),
                'quantity' => $m->quantity,
                'unit_cost' => $m->unit_cost,
                'reason' => $m->reason,
                'by' => $m->creator->name,
                'created_at' => $m->created_at->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * @return array{name: string, kind: string, unit: string, purchase_unit: string|null, purchase_factor: string|null, reorder_level: string}
     */
    private function details(StockItemRequest $request): array
    {
        $hasPurchaseUnit = $request->filled('purchase_unit') && $request->filled('purchase_factor');

        return [
            'name' => $request->string('name')->trim()->toString(),
            'kind' => $request->string('kind')->toString(),
            'unit' => $request->string('unit')->trim()->toString(),
            'purchase_unit' => $hasPurchaseUnit ? $request->string('purchase_unit')->trim()->toString() : null,
            'purchase_factor' => $hasPurchaseUnit ? $request->string('purchase_factor')->toString() : null,
            'reorder_level' => $request->filled('reorder_level') ? $request->string('reorder_level')->toString() : '0',
        ];
    }
}
