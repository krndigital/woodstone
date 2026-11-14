<?php

declare(strict_types=1);

namespace App\Http\Controllers\Money;

use App\Actions\Money\CashMovements;
use App\Http\Controllers\Controller;
use App\Http\Requests\Money\DayRequest;
use App\Models\DayClose;
use App\Support\CashBook;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Day open with opening cash and day close with cash count (FR-4.6). Behind `can:cash.day`.
 */
class DayController extends Controller
{
    public function index(): Response
    {
        $open = DayClose::query()->with('opener:id,name')->whereNull('closed_at')->first();
        $expected = null;
        if ($open !== null) {
            $expected = Money::fromPaisa(Money::toPaisa($open->opening_cash) + CashBook::balance(CashBook::account('cash'), null, $open->opened_at->toDateTimeString(), true));
        }

        return Inertia::render('money/Day', [
            'open' => $open === null ? null : [
                'business_date' => $open->business_date->toDateString(),
                'opening_cash' => $open->opening_cash,
                'opened_by' => $open->opener->name,
                'opened_at' => $open->opened_at->toIso8601String(),
                'expected_cash' => $expected,
            ],
            'suggestedOpening' => Money::fromPaisa(CashBook::balance(CashBook::account('cash'))),
            'history' => DayClose::query()->with(['opener:id,name', 'closer:id,name'])->whereNotNull('closed_at')->latest('business_date')->limit(30)->get()
                ->map(fn (DayClose $d): array => [
                    'id' => $d->id, 'business_date' => $d->business_date->toDateString(),
                    'opening_cash' => $d->opening_cash, 'expected_cash' => $d->expected_cash,
                    'counted_cash' => $d->counted_cash, 'difference' => $d->difference,
                    'closed_by' => $d->closer?->name, 'note' => $d->note,
                ])->values()->all(),
        ]);
    }

    public function open(DayRequest $request, CashMovements $cash): RedirectResponse
    {
        $cash->openDay($request->string('opening_cash')->toString(), $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Day opened.')]);

        return back();
    }

    public function close(DayRequest $request, CashMovements $cash): RedirectResponse
    {
        $day = $cash->closeDay($request->string('counted_cash')->toString(), $request->optionalString('note'), $request->actor());
        $diff = Money::toPaisa((string) $day->difference);
        Inertia::flash('toast', ['type' => $diff === 0 ? 'success' : 'warning', 'message' => $diff === 0
            ? __('Day closed. Cash matches.')
            : __('Day closed. Cash :what by Rs :amount.', ['what' => $diff > 0 ? 'over' : 'short', 'amount' => Money::fromPaisa(abs($diff))])]);

        return back();
    }
}
