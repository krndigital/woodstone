<?php

declare(strict_types=1);

namespace App\Http\Controllers\Money;

use App\Actions\Money\RecordExpense;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Money\ExpenseRequest;
use App\Http\Requests\Money\ReasonRequest;
use App\Http\Requests\Money\RecurringExpenseRequest;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expenses (FR-8). List and add: `expenses.create`. Void and recurring setup: `cash.manage`.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();
        $categoryId = $request->integer('category') ?: null;

        $expenses = Expense::query()->with(['category:id,name', 'account:id,name', 'creator:id,name'])
            ->whereBetween('spent_on', [$from, $to])
            ->when($categoryId !== null, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->orderByDesc('spent_on')->orderByDesc('id')->limit(500)->get();

        $total = 0;
        $rows = [];
        foreach ($expenses as $e) {
            if ($e->voided_at === null) {
                $total += Money::toPaisa($e->amount);
            }
            $rows[] = [
                'id' => $e->id, 'number' => $e->number, 'spent_on' => $e->spent_on->toDateString(),
                'category' => $e->category->name, 'description' => $e->description, 'amount' => $e->amount,
                'account' => $e->account->name, 'kind' => $e->kind, 'by' => $e->creator->name,
                'has_receipt' => $e->receipt_path !== null, 'voided' => $e->voided_at !== null, 'void_reason' => $e->void_reason,
            ];
        }

        $month = now()->format('Y-m');
        $recurring = RecurringExpense::query()->with('category:id,name')->orderBy('day_of_month')->get()->map(fn (RecurringExpense $r): array => [
            'id' => $r->id, 'category_id' => $r->expense_category_id, 'category' => $r->category->name,
            'description' => $r->description, 'amount' => $r->amount, 'day_of_month' => $r->day_of_month,
            'is_active' => $r->is_active, 'due' => $r->is_active && $r->last_month !== $month && (int) now()->format('j') >= $r->day_of_month,
        ])->values()->all();

        return Inertia::render('money/Expenses', [
            'filters' => ['from' => $from, 'to' => $to, 'category' => $categoryId],
            'expenses' => $rows,
            'total' => Money::fromPaisa($total),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'kind'])->toArray(),
            'accounts' => Account::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])->toArray(),
            'recurring' => $recurring,
            'today' => now()->toDateString(),
            'can' => ['manage' => $user->hasPermission(Permission::ManageCash)],
        ]);
    }

    public function store(ExpenseRequest $request, RecordExpense $action): RedirectResponse
    {
        $recurring = $request->filled('recurring_id') ? RecurringExpense::query()->findOrFail($request->integer('recurring_id')) : null;

        $expense = $action->handle(
            ExpenseCategory::query()->findOrFail($request->integer('expense_category_id')),
            $request->string('description')->trim()->toString(),
            $request->string('amount')->toString(),
            Account::query()->findOrFail($request->integer('account_id')),
            $request->string('spent_on')->toString(),
            $request->actor(),
            $request->file('receipt'),
            $recurring !== null ? 'recurring' : null,
            $recurring?->id,
        );

        $recurring?->forceFill(['last_month' => $expense->spent_on->format('Y-m')])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense :number saved.', ['number' => $expense->number])]);

        return back();
    }

    public function void(ReasonRequest $request, Expense $expense, RecordExpense $action): RedirectResponse
    {
        $action->void($expense, $request->string('reason')->trim()->toString(), $request->actor());

        return back();
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        abort_if($expense->receipt_path === null || ! Storage::disk('local')->exists($expense->receipt_path), 404);

        return Storage::disk('local')->download($expense->receipt_path, $expense->number.'-receipt.'.pathinfo($expense->receipt_path, PATHINFO_EXTENSION));
    }

    public function storeRecurring(RecurringExpenseRequest $request): RedirectResponse
    {
        $r = new RecurringExpense($this->recurring($request));
        $r->forceFill(['is_active' => true])->save();
        Audit::record('expense.recurring_created', $r, ['description' => $r->description, 'amount' => $r->amount], $request->actor());

        return back();
    }

    public function updateRecurring(RecurringExpenseRequest $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $recurringExpense->fill($this->recurring($request));
        $recurringExpense->forceFill(['is_active' => $request->boolean('is_active', true)])->save();
        Audit::record('expense.recurring_updated', $recurringExpense, ['description' => $recurringExpense->description, 'is_active' => $recurringExpense->is_active], $request->actor());

        return back();
    }

    /**
     * @return array{expense_category_id: int, description: string, amount: string, day_of_month: int}
     */
    private function recurring(RecurringExpenseRequest $request): array
    {
        return [
            'expense_category_id' => $request->integer('expense_category_id'),
            'description' => $request->string('description')->trim()->toString(),
            'amount' => Money::fromPaisa(Money::toPaisa($request->string('amount')->toString())),
            'day_of_month' => $request->integer('day_of_month'),
        ];
    }
}
