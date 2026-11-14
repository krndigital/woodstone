<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\RecipeRequest;
use App\Models\MenuItem;
use App\Models\RecipeLine;
use App\Models\StockItem;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recipes (FR-7.3) and food cost per plate from current average cost (FR-7.4).
 */
class RecipeController extends Controller
{
    public function index(): Response
    {
        $stock = StockItem::query()->orderBy('name')->get(['id', 'name', 'unit', 'avg_cost', 'is_active'])->keyBy('id');
        $recipes = RecipeLine::query()->get()->groupBy('menu_item_id');

        $items = MenuItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'price'])
            ->map(function (MenuItem $m) use ($recipes, $stock): array {
                $cost4 = 0; // ten-thousandths of a rupee
                $lines = [];
                foreach ($recipes->get($m->id, collect()) as $r) {
                    /** @var RecipeLine $r */
                    $s = $stock->get($r->stock_item_id);
                    if ($s === null) {
                        continue;
                    }
                    $cost4 += Qty::div(Qty::toMilli($r->quantity) * Qty::toCost($s->avg_cost), 1000);
                    $lines[] = ['stock_item_id' => $r->stock_item_id, 'name' => $s->name, 'unit' => $s->unit, 'quantity' => $r->quantity];
                }
                $cost = Qty::div($cost4, 100); // paisa
                $price = Money::toPaisa($m->price);

                return [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                    'price' => $m->price,
                    'cost' => Money::fromPaisa($cost),
                    'margin' => $lines === [] || $price === 0 ? null : intdiv(($price - $cost) * 100, $price),
                    'lines' => $lines,
                ];
            })->values()->all();

        return Inertia::render('stock/Recipes', [
            'menuItems' => $items,
            'stockItems' => $stock->filter(fn (StockItem $s): bool => $s->is_active)->values()->map(fn (StockItem $s): array => ['id' => $s->id, 'name' => $s->name, 'unit' => $s->unit, 'avg_cost' => $s->avg_cost])->all(),
        ]);
    }

    public function update(RecipeRequest $request, MenuItem $menuItem): RedirectResponse
    {
        DB::transaction(function () use ($request, $menuItem): void {
            RecipeLine::query()->where('menu_item_id', $menuItem->id)->delete();
            foreach ((array) $request->validated('lines', []) as $l) {
                $l = (array) $l;
                RecipeLine::query()->create([
                    'menu_item_id' => $menuItem->id,
                    'stock_item_id' => (int) $l['stock_item_id'],
                    'quantity' => Qty::fromMilli(Qty::toMilli((string) $l['quantity'])),
                ]);
            }
        });

        Audit::record('stock.recipe_saved', $menuItem, ['item' => $menuItem->name, 'lines' => count((array) $request->validated('lines', []))]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recipe for :item saved.', ['item' => $menuItem->name])]);

        return back();
    }
}
