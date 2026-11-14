<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Accounting\Ledger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Money\JournalRequest;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ledger accounts, journals and manual journal entry (FR-13). Behind `can:settings.manage`.
 */
class AccountingController extends Controller
{
    public function index(Ledger $ledger): Response
    {
        $ledger->sync();

        $accounts = array_values(array_map(fn (array $a): array => [...$a, 'balance' => Money::fromPaisa($a['balance'])], $ledger->balances()));
        $entries = DB::table('journal_entries')->orderByDesc('id')->limit(80)->get();
        $lines = DB::table('journal_lines')->join('gl_accounts', 'gl_accounts.id', '=', 'journal_lines.gl_account_id')
            ->whereIn('journal_entry_id', $entries->pluck('id'))->get(['journal_entry_id', 'gl_accounts.code', 'gl_accounts.name', 'debit', 'credit'])->groupBy('journal_entry_id');

        return Inertia::render('accounting/Index', [
            'accounts' => $accounts,
            'chart' => DB::table('gl_accounts')->orderBy('code')->get(['code', 'name', 'type'])->toArray(),
            'journals' => $entries->map(fn ($e): array => [
                'id' => (int) $e->id, 'date' => (string) $e->entry_date, 'source' => (string) $e->source_type, 'memo' => $e->memo,
                'lines' => ($lines->get($e->id) ?? collect())->map(fn ($l): array => ['code' => (string) $l->code, 'name' => (string) $l->name, 'debit' => (string) $l->debit, 'credit' => (string) $l->credit])->values()->all(),
            ])->values()->all(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(JournalRequest $request, Ledger $ledger): RedirectResponse
    {
        $lines = [];
        foreach ((array) $request->validated('lines') as $l) {
            $l = (array) $l;
            $lines[] = [
                'code' => (string) $l['code'],
                'debit' => Money::toPaisa((string) ($l['debit'] ?? '0') ?: '0'),
                'credit' => Money::toPaisa((string) ($l['credit'] ?? '0') ?: '0'),
            ];
        }
        $dr = array_sum(array_column($lines, 'debit'));
        if ($dr === 0 || $dr !== array_sum(array_column($lines, 'credit'))) {
            throw ValidationException::withMessages(['lines' => __('Debits and credits must be equal and more than zero.')]);
        }

        $ledger->manual($request->string('entry_date')->toString(), $request->string('memo')->trim()->toString(), $lines, $request->actor());
        Audit::record('ledger.manual_journal', null, ['memo' => $request->string('memo')->toString(), 'amount' => Money::fromPaisa($dr)], $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal saved.')]);

        return back();
    }
}
