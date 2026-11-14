<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Models\User;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Automatic double-entry ledger (FR-13.1). Every money and stock record in the system is turned
 * into a balanced journal once (keyed by source type + id), so nobody posts journals by hand.
 * `sync()` is safe to run any time; it only adds what is missing. Manual journals are Super Admin
 * only (FR-13.4). Amounts are integer paisa.
 *
 * Cash side: every cash-book row is journalled against a clearing or counter account, and the
 * business documents (bills, purchases, returns, stock movements) are journalled against the
 * same clearing accounts, so each side balances on its own.
 */
final class Ledger
{
    /** Money account code (cash book) → ledger account code. */
    private const CASH = ['cash' => '1000', 'bank' => '1010', 'qr' => '1020', 'card' => '1030'];

    /** @var array<string, int> */
    private array $ids = [];

    public function sync(): int
    {
        $count = 0;
        DB::transaction(function () use (&$count): void {
            $count += $this->bills();
            $count += $this->salesReturns();
            $count += $this->cashBook();
            $count += $this->purchases();
            $count += $this->purchaseReturns();
            $count += $this->stock();
            $count += $this->payslips();
            $count += $this->customerOpenings();
            $count += $this->supplierOpenings();
            $count += $this->forfeits();
        });

        return $count;
    }

    /**
     * Manual journal (Super Admin only, checked by the caller).
     *
     * @param  list<array{code: string, debit: int, credit: int}>  $lines
     */
    public function manual(string $date, string $memo, array $lines, User $by): int
    {
        return $this->post('manual', null, $date, $memo, $lines, $by->id);
    }

    /**
     * Ledger account id for a code; expense categories get their own account on first use.
     */
    public function accountId(string $code, ?string $name = null, string $type = 'expense'): int
    {
        if (isset($this->ids[$code])) {
            return $this->ids[$code];
        }
        $id = DB::table('gl_accounts')->where('code', $code)->value('id');
        if ($id === null) {
            $id = DB::table('gl_accounts')->insertGetId(['code' => $code, 'name' => $name ?? $code, 'type' => $type, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $this->ids[$code] = (int) $id;
    }

    public static function expenseCode(int $categoryId): string
    {
        return '6'.str_pad((string) $categoryId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{code: string, debit: int, credit: int}>  $lines
     */
    private function post(string $type, ?int $sourceId, string $date, string $memo, array $lines, ?int $by = null): int
    {
        $lines = array_values(array_filter($lines, fn (array $l): bool => $l['debit'] !== 0 || $l['credit'] !== 0));
        if ($lines === []) {
            return 0;
        }
        // Negative amounts flip sides so every stored line is positive.
        foreach ($lines as $i => $l) {
            if ($l['debit'] < 0 || $l['credit'] < 0) {
                $lines[$i] = ['code' => $l['code'], 'debit' => max(0, $l['debit']) + max(0, -$l['credit']), 'credit' => max(0, $l['credit']) + max(0, -$l['debit'])];
            }
        }
        $dr = array_sum(array_column($lines, 'debit'));
        $cr = array_sum(array_column($lines, 'credit'));
        if ($dr !== $cr) {
            throw new RuntimeException("Journal for {$type} #{$sourceId} does not balance ({$dr} vs {$cr}).");
        }

        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_date' => substr($date, 0, 10), 'source_type' => $type, 'source_id' => $sourceId,
            'memo' => mb_substr($memo, 0, 200), 'created_by' => $by, 'created_at' => now(),
        ]);
        foreach ($lines as $l) {
            DB::table('journal_lines')->insert([
                'journal_entry_id' => $entryId, 'gl_account_id' => $this->accountId($l['code']),
                'debit' => Money::fromPaisa($l['debit']), 'credit' => Money::fromPaisa($l['credit']),
            ]);
        }

        return 1;
    }

    /**
     * @return list<int>
     */
    private function done(string $type): array
    {
        return array_values(array_map('intval', DB::table('journal_entries')->where('source_type', $type)->pluck('source_id')->all()));
    }

    private function bills(): int
    {
        $n = 0;
        $done = $this->done('bill');
        foreach (DB::table('bills')->whereNotIn('id', $done)->orderBy('id')->get() as $b) {
            $clearing = 0;
            $due = 0;
            $points = 0;
            $advance = 0;
            foreach (DB::table('bill_payments')->where('bill_id', $b->id)->get(['method', 'amount']) as $p) {
                $a = Money::toPaisa((string) $p->amount);
                if ($p->method === 'due') {
                    $due += $a;
                } elseif ($p->method === 'points') {
                    $points += $a;
                } elseif ($p->method === 'advance') {
                    $advance += $a;
                } else {
                    $clearing += $a;
                }
            }
            $sales = Money::toPaisa((string) $b->subtotal) - Money::toPaisa((string) $b->discount_amount);
            $n += $this->post('bill', (int) $b->id, (string) $b->created_at, 'Bill '.$b->number, [
                ['code' => '1190', 'debit' => $clearing, 'credit' => 0],
                ['code' => '1100', 'debit' => $due, 'credit' => 0],
                ['code' => '5200', 'debit' => $points, 'credit' => 0],
                ['code' => '2200', 'debit' => $advance, 'credit' => 0],
                ['code' => '4000', 'debit' => 0, 'credit' => $sales],
                ['code' => '4010', 'debit' => 0, 'credit' => Money::toPaisa((string) $b->service_amount)],
                ['code' => '2100', 'debit' => 0, 'credit' => Money::toPaisa((string) $b->vat_amount)],
                ['code' => '4900', 'debit' => 0, 'credit' => Money::toPaisa((string) ($b->round_off ?? '0'))],
            ]);
        }

        return $n;
    }

    private function salesReturns(): int
    {
        $n = 0;
        $done = $this->done('sales_return');
        foreach (DB::table('sales_returns')->join('bills', 'bills.id', '=', 'sales_returns.bill_id')->whereNotIn('sales_returns.id', $done)->orderBy('sales_returns.id')
            ->get(['sales_returns.id', 'sales_returns.amount', 'sales_returns.refund_method', 'sales_returns.created_at', 'bills.number']) as $r) {
            $a = Money::toPaisa((string) $r->amount);
            $n += $this->post('sales_return', (int) $r->id, (string) $r->created_at, 'Return on bill '.$r->number, [
                ['code' => '4100', 'debit' => $a, 'credit' => 0],
                ['code' => $r->refund_method === 'due' ? '1100' : '1190', 'debit' => 0, 'credit' => $a],
            ]);
        }

        return $n;
    }

    /**
     * Every cash-book row: the money account against its counter account.
     */
    private function cashBook(): int
    {
        $n = 0;
        $done = $this->done('cash');
        $expenses = DB::table('expenses')->pluck('expense_category_id', 'id');
        $kinds = DB::table('expenses')->pluck('kind', 'id');
        $catNames = DB::table('expense_categories')->pluck('name', 'id');
        foreach (DB::table('account_transactions')->join('accounts', 'accounts.id', '=', 'account_transactions.account_id')->whereNotIn('account_transactions.id', $done)->orderBy('account_transactions.id')
            ->get(['account_transactions.*', 'accounts.code as account_code']) as $t) {
            $a = Money::toPaisa((string) $t->amount); // + in, − out
            $cash = self::CASH[(string) $t->account_code] ?? '1010';
            $counter = match ((string) $t->source_type) {
                'bill_payment', 'sales_return' => '1190',
                'due_receipt' => '1100',
                'purchase', 'supplier_payment' => '2000',
                'advance' => '1110',
                'reservation' => '2200',
                'transfer' => '1195',
                'day_close' => '6980',
                'expense', 'expense_void' => $this->expenseAccount((int) ($expenses[(int) $t->source_id] ?? 0), (string) ($kinds[(int) $t->source_id] ?? 'operating'), (string) ($catNames[(int) ($expenses[(int) $t->source_id] ?? 0)] ?? 'Expense')),
                'manual' => match ((string) $t->category) {
                    'owner_capital' => '3000',
                    'owner_withdrawal' => '3100',
                    'loan' => '2400',
                    default => $a >= 0 ? '4950' : '6990',
                },
                default => '6990',
            };
            $n += $this->post('cash', (int) $t->id, (string) $t->business_date, (string) ($t->note ?? $t->source_type), [
                ['code' => $cash, 'debit' => $a, 'credit' => 0],
                ['code' => $counter, 'debit' => 0, 'credit' => $a],
            ]);
        }

        return $n;
    }

    private function expenseAccount(int $categoryId, string $kind, string $name): string
    {
        if ($kind === 'investment') {
            return '1500';
        }
        $code = self::expenseCode($categoryId);
        $this->accountId($code, 'Expense: '.$name, 'expense');

        return $code;
    }

    private function purchases(): int
    {
        $n = 0;
        $done = $this->done('purchase');
        foreach (DB::table('purchases')->whereNotIn('id', $done)->orderBy('id')->get() as $p) {
            $stock = 0;
            $assets = 0;
            foreach (DB::table('purchase_lines')->join('stock_items', 'stock_items.id', '=', 'purchase_lines.stock_item_id')->where('purchase_id', $p->id)->get(['purchase_lines.line_total', 'stock_items.kind']) as $l) {
                $v = Money::toPaisa((string) $l->line_total);
                if ($l->kind === 'asset') {
                    $assets += $v;
                } else {
                    $stock += $v;
                }
            }
            $n += $this->post('purchase', (int) $p->id, (string) $p->purchased_on, 'Purchase '.$p->number, [
                ['code' => '1200', 'debit' => $stock, 'credit' => 0],
                ['code' => '1500', 'debit' => $assets, 'credit' => 0],
                ['code' => '2000', 'debit' => 0, 'credit' => $stock + $assets],
            ]);
        }

        return $n;
    }

    private function purchaseReturns(): int
    {
        $n = 0;
        $done = $this->done('purchase_return');
        foreach (DB::table('purchase_returns')->whereNotIn('id', $done)->orderBy('id')->get() as $r) {
            $a = Money::toPaisa((string) $r->amount);
            $n += $this->post('purchase_return', (int) $r->id, (string) $r->created_at, 'Return to supplier: '.$r->reason, [
                ['code' => '2000', 'debit' => $a, 'credit' => 0],
                ['code' => '1200', 'debit' => 0, 'credit' => $a],
            ]);
        }

        return $n;
    }

    /**
     * Stock movements valued at the cost stored on each movement (purchases and purchase returns
     * are journalled from their own documents).
     */
    private function stock(): int
    {
        $n = 0;
        $done = $this->done('stock');
        foreach (DB::table('stock_movements')->whereNotIn('id', $done)->whereNotIn('type', ['purchase'])->orderBy('id')->get() as $m) {
            if ($m->type === 'return' && $m->reference_type === 'purchase_return') {
                continue;
            }
            $value = Qty::div(Qty::toMilli((string) $m->quantity) * Qty::toCost((string) ($m->unit_cost ?? '0')), 100000); // paisa, signed
            $counter = match ((string) $m->type) {
                'sale' => '5000',
                'return' => '5000', // ingredients back from a cancelled bill
                'opening' => '3050',
                default => '5100', // wastage, staff meal, adjustment, count
            };
            $n += $this->post('stock', (int) $m->id, (string) $m->created_at, 'Stock '.$m->type, [
                ['code' => '1200', 'debit' => $value, 'credit' => 0],
                ['code' => $counter, 'debit' => 0, 'credit' => $value],
            ]);
        }

        return $n;
    }

    /**
     * Salary advances recovered from pay: the advance becomes salary expense.
     */
    private function payslips(): int
    {
        $n = 0;
        $done = $this->done('payslip');
        $salary = DB::table('expense_categories')->where('name', 'Salary')->value('id');
        foreach (DB::table('payslips')->where('status', 'paid')->whereNotIn('id', $done)->orderBy('id')->get() as $p) {
            $a = Money::toPaisa((string) $p->advance_deduction);
            if ($a === 0) {
                DB::table('journal_entries')->insert(['entry_date' => substr((string) $p->paid_at, 0, 10), 'source_type' => 'payslip', 'source_id' => $p->id, 'memo' => 'Payslip '.$p->month.' (no advance)', 'created_at' => now()]);

                continue;
            }
            $code = $this->expenseAccount((int) $salary, 'operating', 'Salary');
            $n += $this->post('payslip', (int) $p->id, (string) $p->paid_at, 'Advance recovered from salary '.$p->month, [
                ['code' => $code, 'debit' => $a, 'credit' => 0],
                ['code' => '1110', 'debit' => 0, 'credit' => $a],
            ]);
        }

        return $n;
    }

    private function customerOpenings(): int
    {
        $n = 0;
        $done = $this->done('customer_opening');
        foreach (DB::table('ledger_entries')->where('type', 'opening')->whereNotIn('id', $done)->get() as $e) {
            $a = Money::toPaisa((string) $e->amount);
            $n += $this->post('customer_opening', (int) $e->id, (string) $e->created_at, 'Opening due', [
                ['code' => '1100', 'debit' => $a, 'credit' => 0],
                ['code' => '3050', 'debit' => 0, 'credit' => $a],
            ]);
        }

        return $n;
    }

    private function supplierOpenings(): int
    {
        $n = 0;
        $done = $this->done('supplier_opening');
        foreach (DB::table('suppliers')->where('opening_balance', '!=', 0)->whereNotIn('id', $done)->get() as $s) {
            $a = Money::toPaisa((string) $s->opening_balance);
            $n += $this->post('supplier_opening', (int) $s->id, (string) $s->created_at, 'Opening payable · '.$s->name, [
                ['code' => '3050', 'debit' => $a, 'credit' => 0],
                ['code' => '2000', 'debit' => 0, 'credit' => $a],
            ]);
        }

        return $n;
    }

    /**
     * Advances kept when a guest cancels or does not come become other income.
     */
    private function forfeits(): int
    {
        $n = 0;
        $done = $this->done('reservation_forfeit');
        foreach (DB::table('reservations')->whereIn('status', ['cancelled', 'no_show'])->whereNotIn('id', $done)->get() as $r) {
            $kept = Money::toPaisa((string) $r->advance) - Money::toPaisa((string) $r->advance_used);
            if ($kept <= 0) {
                DB::table('journal_entries')->insert(['entry_date' => substr((string) $r->updated_at, 0, 10), 'source_type' => 'reservation_forfeit', 'source_id' => $r->id, 'memo' => 'Booking closed, nothing kept', 'created_at' => now()]);

                continue;
            }
            $n += $this->post('reservation_forfeit', (int) $r->id, (string) $r->updated_at, 'Booking advance kept · '.$r->name, [
                ['code' => '2200', 'debit' => $kept, 'credit' => 0],
                ['code' => '4950', 'debit' => 0, 'credit' => $kept],
            ]);
        }

        return $n;
    }

    /**
     * Balance per ledger account up to a date (debit − credit, paisa).
     *
     * @return array<int, array{code: string, name: string, type: string, balance: int}>
     */
    public function balances(?string $upTo = null, ?string $from = null): array
    {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'journal_lines.gl_account_id')
            ->when($upTo !== null, fn ($q) => $q->where('journal_entries.entry_date', '<=', $upTo))
            ->when($from !== null, fn ($q) => $q->where('journal_entries.entry_date', '>=', $from))
            ->groupBy('gl_accounts.id', 'gl_accounts.code', 'gl_accounts.name', 'gl_accounts.type')
            ->orderBy('gl_accounts.code')
            ->selectRaw('gl_accounts.id, gl_accounts.code, gl_accounts.name, gl_accounts.type, SUM(journal_lines.debit) as dr, SUM(journal_lines.credit) as cr')
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = ['code' => (string) $r->code, 'name' => (string) $r->name, 'type' => (string) $r->type, 'balance' => Money::fromDbSum($r->dr) - Money::fromDbSum($r->cr)];
        }

        return $out;
    }
}
