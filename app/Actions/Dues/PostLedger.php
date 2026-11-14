<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Audit;
use App\Support\ManagerApproval;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Due payments and moving dues between customers (step 8, FR-10, D-07). Ledger rows only ever
 * get added. Balances are locked per customer while posting.
 */
final class PostLedger
{
    public function receive(Customer $customer, PaymentMethod $method, string $amount, ?string $reference, ?string $note, User $by): LedgerEntry
    {
        $paisa = Money::toPaisa($amount);

        return DB::transaction(function () use ($customer, $method, $paisa, $reference, $note, $by): LedgerEntry {
            $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $balance = $customer->balancePaisa();

            if ($paisa <= 0 || $paisa > $balance) {
                throw ValidationException::withMessages(['amount' => __('Amount must be more than zero and not more than the due (:due).', ['due' => Money::fromPaisa($balance)])]);
            }

            $entry = $this->post($customer, LedgerType::Payment, -$paisa, $by, ['method' => $method, 'reference' => $reference, 'note' => $note]);
            Audit::record('dues.payment_received', $customer, ['amount' => Money::fromPaisa($paisa), 'method' => $method->value, 'reference' => $reference], $by);

            return $entry;
        });
    }

    public function move(Customer $from, Customer $to, string $amount, string $note, User $by, ?string $approverUsername, ?string $approverSecret): void
    {
        $paisa = Money::toPaisa($amount);

        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_customer_id' => __('Choose a different customer.')]);
        }

        $approver = ManagerApproval::resolve($by, $approverUsername, $approverSecret);

        DB::transaction(function () use ($from, $to, $paisa, $note, $by, $approver): void {
            // Lock both in id order to avoid deadlocks.
            $locked = Customer::query()->whereKey([$from->id, $to->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked->get($from->id);
            $target = $locked->get($to->id);
            if ($source === null || $target === null || ! $target->is_active) {
                throw ValidationException::withMessages(['to_customer_id' => __('That customer account is turned off.')]);
            }

            $balance = $source->balancePaisa();
            if ($paisa <= 0 || $paisa > $balance) {
                throw ValidationException::withMessages(['amount' => __('Amount must be more than zero and not more than the due (:due).', ['due' => Money::fromPaisa($balance)])]);
            }

            $this->post($source, LedgerType::TransferOut, -$paisa, $by, ['related_customer_id' => $target->id, 'note' => $note]);
            $this->post($target, LedgerType::TransferIn, $paisa, $by, ['related_customer_id' => $source->id, 'note' => $note]);

            Audit::record('dues.moved', $source, [
                'amount' => Money::fromPaisa($paisa), 'to' => $target->name, 'to_id' => $target->id,
                'note' => $note, 'approved_by' => $approver->username,
            ], $by);
        });
    }

    /**
     * @param  array{method?: PaymentMethod, reference?: string|null, note?: string|null, related_customer_id?: int}  $extra
     */
    private function post(Customer $customer, LedgerType $type, int $paisa, User $by, array $extra): LedgerEntry
    {
        $entry = new LedgerEntry;
        $entry->forceFill([
            'customer_id' => $customer->id,
            'type' => $type,
            'amount' => Money::fromPaisa($paisa),
            'method' => $extra['method'] ?? null,
            'reference' => $extra['reference'] ?? null,
            'note' => $extra['note'] ?? null,
            'related_customer_id' => $extra['related_customer_id'] ?? null,
            'created_by' => $by->id,
            'created_at' => now(),
        ])->save();

        return $entry;
    }
}
