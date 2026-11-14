<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\SupplierRequest;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suppliers (FR-6.1). Amount owed = purchases not paid at the time. Supplier payments and the
 * payable ledger come with step 10.
 */
class SupplierController extends Controller
{
    public function index(): Response
    {
        $owed = [];
        foreach (Purchase::query()->get(['supplier_id', 'total', 'paid_amount']) as $p) {
            $owed[$p->supplier_id] = ($owed[$p->supplier_id] ?? 0) + Money::toPaisa($p->total) - Money::toPaisa($p->paid_amount);
        }

        $suppliers = Supplier::query()->withCount('purchases')->orderBy('name')->get()->map(fn (Supplier $s): array => [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'pan' => $s->pan,
            'address' => $s->address,
            'is_active' => $s->is_active,
            'purchases' => (int) $s->getAttribute('purchases_count'),
            'owed' => Money::fromPaisa($owed[$s->id] ?? 0),
        ])->values()->all();

        return Inertia::render('stock/Suppliers', ['suppliers' => $suppliers]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = new Supplier($this->details($request));
        $supplier->forceFill(['is_active' => true])->save();
        Audit::record('stock.supplier_created', $supplier, ['name' => $supplier->name]);

        return back();
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->fill($this->details($request));
        $supplier->forceFill(['is_active' => $request->boolean('is_active', true)])->save();
        Audit::record('stock.supplier_updated', $supplier, ['name' => $supplier->name, 'is_active' => $supplier->is_active]);

        return back();
    }

    /**
     * @return array{name: string, phone: string|null, pan: string|null, address: string|null}
     */
    private function details(SupplierRequest $request): array
    {
        return [
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->filled('phone') ? $request->string('phone')->trim()->toString() : null,
            'pan' => $request->filled('pan') ? $request->string('pan')->toString() : null,
            'address' => $request->filled('address') ? $request->string('address')->trim()->toString() : null,
        ];
    }
}
