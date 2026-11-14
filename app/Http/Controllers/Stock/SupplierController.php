<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Actions\Money\CashMovements;
use App\Actions\Stock\ReturnToSupplier;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Money\PurchaseReturnRequest;
use App\Http\Requests\Money\SupplierPaymentRequest;
use App\Http\Requests\Stock\SupplierRequest;
use App\Models\Account;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Payables;
use App\Support\SupplierBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suppliers (FR-6.1), what we owe them (FR-15.1), payments (FR-15.2) and returns (FR-6.7).
 * Behind `can:stock.manage`; paying also needs `cash.manage`.
 */
class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $owed = SupplierBalance::all();
        $ageing = Payables::ageing();

        $suppliers = Supplier::query()->withCount('purchases')->orderBy('name')->get()->map(fn (Supplier $s): array => [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'pan' => $s->pan,
            'address' => $s->address,
            'is_active' => $s->is_active,
            'purchases' => (int) $s->getAttribute('purchases_count'),
            'owed' => Money::fromPaisa($owed[$s->id] ?? 0),
            'credit_days' => $s->credit_days,
            'opening_balance' => $s->opening_balance,
            'overdue' => Money::fromPaisa(($ageing[$s->id]['d1_30'] ?? 0) + ($ageing[$s->id]['d31_60'] ?? 0) + ($ageing[$s->id]['d61_90'] ?? 0) + ($ageing[$s->id]['d90_plus'] ?? 0)),
            'next_7' => Money::fromPaisa($ageing[$s->id]['next_7'] ?? 0),
        ])->values()->all();

        return Inertia::render('stock/Suppliers', [
            'suppliers' => $suppliers,
            'accounts' => Account::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])->toArray(),
            'items' => StockItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit'])->toArray(),
            'today' => now()->toDateString(),
            'can' => ['pay' => $user->hasPermission(Permission::ManageCash)],
        ]);
    }

    public function pay(SupplierPaymentRequest $request, Supplier $supplier, CashMovements $cash): RedirectResponse
    {
        $cash->paySupplier(
            $supplier,
            $request->string('amount')->toString(),
            Account::query()->findOrFail($request->integer('account_id')),
            $request->string('paid_on')->toString(),
            $request->optionalString('reference'),
            $request->optionalString('note'),
            $request->actor(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment to :name saved.', ['name' => $supplier->name])]);

        return back();
    }

    public function returnGoods(PurchaseReturnRequest $request, Supplier $supplier, ReturnToSupplier $action): RedirectResponse
    {
        $action->handle(
            $supplier,
            StockItem::query()->findOrFail($request->integer('stock_item_id')),
            $request->string('quantity')->toString(),
            $request->string('amount')->toString(),
            $request->string('reason')->trim()->toString(),
            $request->actor(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Return saved. Stock and amount owed updated.')]);

        return back();
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = new Supplier($this->details($request));
        // Opening balance is set once, on go-live (FR-15.10); later changes go through payments/returns.
        $supplier->forceFill([
            'is_active' => true,
            'credit_days' => $request->integer('credit_days', 30),
            'opening_balance' => Money::fromPaisa(Money::toPaisa($request->filled('opening_balance') ? $request->string('opening_balance')->toString() : '0')),
        ])->save();
        Audit::record('stock.supplier_created', $supplier, ['name' => $supplier->name, 'opening_balance' => $supplier->opening_balance]);

        return back();
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->fill($this->details($request));
        $supplier->forceFill(['is_active' => $request->boolean('is_active', true), 'credit_days' => $request->integer('credit_days', $supplier->credit_days)])->save();
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
