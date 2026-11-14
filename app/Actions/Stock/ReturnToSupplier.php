<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\PurchaseReturn;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase return (FR-6.7): stock goes out and we owe the supplier less.
 */
final class ReturnToSupplier
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(Supplier $supplier, StockItem $item, string $quantity, string $amount, string $reason, User $by, ?int $purchaseId = null): PurchaseReturn
    {
        $milli = Qty::toMilli($quantity);
        $paisa = Money::toPaisa($amount);
        if ($milli <= 0 || $paisa < 0) {
            throw ValidationException::withMessages(['quantity' => __('Quantity must be more than zero.')]);
        }

        return DB::transaction(function () use ($supplier, $item, $milli, $paisa, $reason, $by, $purchaseId): PurchaseReturn {
            $return = new PurchaseReturn;
            $return->forceFill([
                'supplier_id' => $supplier->id, 'purchase_id' => $purchaseId, 'stock_item_id' => $item->id,
                'quantity' => Qty::fromMilli($milli), 'amount' => Money::fromPaisa($paisa), 'reason' => $reason,
                'created_by' => $by->id, 'created_at' => now(),
            ])->save();

            $this->ledger->move($item, StockMovementType::Return, -$milli, null, $by, 'Returned to '.$supplier->name.': '.$reason, 'purchase_return', $return->id);
            Audit::record('stock.purchase_return', $return, ['supplier' => $supplier->name, 'item' => $item->name, 'quantity' => $return->quantity, 'amount' => $return->amount], $by);

            return $return;
        });
    }
}
