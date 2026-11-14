<?php

declare(strict_types=1);

namespace App\Http\Controllers\Money;

use App\Actions\Money\CashMovements;
use App\Http\Controllers\Controller;
use App\Http\Requests\Money\ManualEntryRequest;
use App\Http\Requests\Money\TransferRequest;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Support\CashBook;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily cash book per account (FR-9.5), manual entries (FR-9.3) and transfers (FR-9.4).
 * Behind `can:cash.manage`.
 */
class CashBookController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $this->date($request->string('from')->toString(), now()->startOfMonth()->toDateString());
        $to = $this->date($request->string('to')->toString(), now()->toDateString());
        $accounts = Account::query()->where('is_active', true)->orderBy('id')->get();
        $accountId = $request->integer('account') ?: $accounts->first()?->id;
        $account = $accounts->firstWhere('id', $accountId);

        $summary = [];
        foreach ($accounts as $a) {
            $opening = CashBook::balance($a, CarbonImmutable::parse($from)->subDay()->toDateString());
            $closing = CashBook::balance($a, $to);
            $in = Money::fromDbSum(AccountTransaction::query()->where('account_id', $a->id)->whereBetween('business_date', [$from, $to])->where('amount', '>', 0)->sum('amount'));
            $summary[] = [
                'id' => $a->id, 'code' => $a->code, 'name' => $a->name,
                'opening' => Money::fromPaisa($opening), 'in' => Money::fromPaisa($in),
                'out' => Money::fromPaisa($in - ($closing - $opening)), 'closing' => Money::fromPaisa($closing),
                'balance' => Money::fromPaisa(CashBook::balance($a)),
            ];
        }

        $rows = [];
        if ($account !== null) {
            $running = CashBook::balance($account, CarbonImmutable::parse($from)->subDay()->toDateString());
            foreach (AccountTransaction::query()->with('creator:id,name')->where('account_id', $account->id)->whereBetween('business_date', [$from, $to])->orderBy('business_date')->orderBy('id')->limit(1000)->get() as $t) {
                $running += Money::toPaisa($t->amount);
                $rows[] = [
                    'id' => $t->id, 'date' => $t->business_date->toDateString(), 'source' => $t->source_type,
                    'category' => $t->category, 'note' => $t->note, 'amount' => $t->amount,
                    'balance' => Money::fromPaisa($running), 'by' => $t->creator->name,
                ];
            }
        }

        return Inertia::render('money/CashBook', [
            'filters' => ['from' => $from, 'to' => $to, 'account' => $accountId],
            'accounts' => $summary,
            'rows' => $rows,
        ]);
    }

    public function manual(ManualEntryRequest $request, CashMovements $cash): RedirectResponse
    {
        $cash->manual(
            Account::query()->findOrFail($request->integer('account_id')),
            $request->string('direction')->toString(),
            $request->string('amount')->toString(),
            $request->string('category')->toString(),
            $request->string('note')->trim()->toString(),
            $request->actor(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entry saved.')]);

        return back();
    }

    public function transfer(TransferRequest $request, CashMovements $cash): RedirectResponse
    {
        $cash->transfer(
            Account::query()->findOrFail($request->integer('from_account_id')),
            Account::query()->findOrFail($request->integer('to_account_id')),
            $request->string('amount')->toString(),
            $request->optionalString('note'),
            $request->actor(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer saved.')]);

        return back();
    }

    private function date(string $value, string $default): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : $default;
    }
}
