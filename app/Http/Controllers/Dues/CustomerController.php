<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dues;

use App\Actions\Dues\PostLedger;
use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dues\CustomerGroupRequest;
use App\Http\Requests\Dues\CustomerRequest;
use App\Http\Requests\Dues\MoveDueRequest;
use App\Http\Requests\Dues\ReceivePaymentRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\LedgerEntry;
use App\Models\LoyaltyEntry;
use App\Models\User;
use App\Support\Audit;
use App\Support\DuesData;
use App\Support\Loyalty;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customers, groups and dues (step 8). Behind `can:dues.manage`. Changing a credit limit or
 * turning an account off needs `billing.approve_override`.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $customers = [];
        $totals = ['balance' => 0, 'age_0_30' => 0, 'age_31_60' => 0, 'age_60_plus' => 0];
        $points = DB::table('loyalty_entries')->groupBy('customer_id')->selectRaw('customer_id, SUM(points) as p')->pluck('p', 'customer_id');
        $spent = [];
        foreach (DB::table('bills')->whereNotNull('customer_id')->where('created_at', '>=', now()->subYear())->get(['customer_id', 'total']) as $b) {
            $spent[(int) $b->customer_id] = ($spent[(int) $b->customer_id] ?? 0) + Money::toPaisa((string) $b->total);
        }
        $birthdays = [];
        foreach (Customer::query()->with('group')->orderBy('name')->get() as $c) {
            foreach (['birthday' => 'Birthday', 'anniversary' => 'Anniversary'] as $field => $label) {
                $d = $c->{$field};
                if ($d instanceof CarbonInterface) {
                    $next = $d->setYear((int) now()->format('Y'));
                    if ($next->lessThan(now()->startOfDay())) {
                        $next = $next->addYear();
                    }
                    if ($next->lessThanOrEqualTo(now()->addDays(7)->endOfDay())) {
                        $birthdays[] = ['name' => $c->name, 'phone' => $c->phone, 'what' => $label, 'date' => $next->toDateString()];
                    }
                }
            }
            $s = DuesData::summary($c);
            foreach ($totals as $k => $v) {
                $totals[$k] = $v + $s[$k];
            }
            $customers[] = [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'group_id' => $c->customer_group_id,
                'group' => $c->group?->name,
                'note' => $c->note,
                'credit_limit' => $c->credit_limit,
                'is_active' => $c->is_active,
                'balance' => Money::fromPaisa($s['balance']),
                'age_0_30' => Money::fromPaisa($s['age_0_30']),
                'age_31_60' => Money::fromPaisa($s['age_31_60']),
                'age_60_plus' => Money::fromPaisa($s['age_60_plus']),
                'oldest' => $s['oldest'],
                'email' => $c->email,
                'birthday' => $c->birthday?->toDateString(),
                'anniversary' => $c->anniversary?->toDateString(),
                'preferred_seat' => $c->preferred_seat,
                'points' => (int) ($points[$c->id] ?? 0),
                'tier' => Loyalty::tier($spent[$c->id] ?? 0),
            ];
        }

        return Inertia::render('customers/Index', [
            'customers' => $customers,
            'totals' => array_map(fn (int $p): string => Money::fromPaisa($p), $totals),
            'groups' => CustomerGroup::query()->orderBy('name')->get(['id', 'name'])->toArray(),
            'methods' => array_values(array_map(fn (PaymentMethod $m): array => ['value' => $m->value, 'label' => $m->label()], array_filter(PaymentMethod::receivable(), fn (PaymentMethod $m): bool => $m->isEnabled()))),
            'defaultLimit' => Settings::string('default_credit_limit'),
            'birthdays' => $birthdays,
            'can' => ['approve' => $user->hasPermission(Permission::ApproveOverride)],
        ]);
    }

    public function show(Customer $customer): Response
    {
        $entries = LedgerEntry::query()
            ->with(['bill:id,number', 'relatedCustomer:id,name', 'creator:id,name'])
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        $running = 0;
        $rows = [];
        foreach ($entries as $e) {
            $running += Money::toPaisa($e->amount);
            $rows[] = [
                'id' => $e->id,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'amount' => $e->amount,
                'balance' => Money::fromPaisa($running),
                'bill_id' => $e->bill_id,
                'bill' => $e->bill?->number,
                'related' => $e->relatedCustomer?->name,
                'method' => $e->method?->label(),
                'reference' => $e->reference,
                'note' => $e->note,
                'by' => $e->creator->name,
                'created_at' => $e->created_at->toIso8601String(),
            ];
        }

        $history = Loyalty::history($customer);
        $loyalty = LoyaltyEntry::query()->where('customer_id', $customer->id)->latest('id')->limit(50)->get()->map(fn (LoyaltyEntry $l): array => [
            'id' => $l->id, 'points' => $l->points, 'type' => $l->type, 'note' => $l->note,
            'expires_on' => $l->expires_on?->toDateString(), 'created_at' => $l->created_at->toIso8601String(),
        ])->values()->all();

        return Inertia::render('customers/Show', [
            'customer' => ['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone, 'credit_limit' => $customer->credit_limit, 'is_active' => $customer->is_active,
                'email' => $customer->email, 'birthday' => $customer->birthday?->toDateString(), 'anniversary' => $customer->anniversary?->toDateString(), 'preferred_seat' => $customer->preferred_seat],
            'history' => [...$history, 'spent' => Money::fromPaisa($history['spent']), 'spent_12m' => Money::fromPaisa($history['spent_12m'])],
            'points' => Loyalty::balance($customer),
            'pointValue' => Settings::string('loyalty_point_value'),
            'loyalty' => $loyalty,
            'summary' => array_map(fn (int|string|null $v): ?string => is_int($v) ? Money::fromPaisa($v) : $v, DuesData::summary($customer)),
            'entries' => array_reverse($rows),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $user = $request->actor();
        $limit = $request->filled('credit_limit') && $user->hasPermission(Permission::ApproveOverride)
            ? $request->string('credit_limit')->toString()
            : Settings::string('default_credit_limit');

        $customer = new Customer($this->details($request));
        $customer->forceFill(['credit_limit' => Money::fromPaisa(Money::toPaisa($limit)), 'is_active' => true])->save();

        Audit::record('customer.created', $customer, ['name' => $customer->name, 'credit_limit' => $customer->credit_limit], $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $customer->name])]);

        return back();
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $user = $request->actor();
        $before = ['credit_limit' => $customer->credit_limit, 'is_active' => $customer->is_active];

        $customer->fill($this->details($request));

        $wantsLimit = $request->filled('credit_limit') ? Money::fromPaisa(Money::toPaisa($request->string('credit_limit')->toString())) : $customer->credit_limit;
        $wantsActive = $request->boolean('is_active', $customer->is_active);

        if (($wantsLimit !== $customer->credit_limit || $wantsActive !== $customer->is_active) && ! $user->hasPermission(Permission::ApproveOverride)) {
            throw ValidationException::withMessages(['credit_limit' => __('Only a manager can change the due limit or turn an account off.')]);
        }

        $customer->forceFill(['credit_limit' => $wantsLimit, 'is_active' => $wantsActive])->save();

        Audit::record('customer.updated', $customer, [
            'name' => $customer->name,
            'before' => $before,
            'after' => ['credit_limit' => $customer->credit_limit, 'is_active' => $customer->is_active],
        ], $user);

        return back();
    }

    public function receive(ReceivePaymentRequest $request, Customer $customer, PostLedger $ledger): RedirectResponse
    {
        $method = PaymentMethod::from($request->string('method')->toString());
        if (! $method->isEnabled()) {
            throw ValidationException::withMessages(['method' => __(':method payments are turned off in settings.', ['method' => $method->label()])]);
        }

        $entry = $ledger->receive(
            $customer,
            $method,
            $request->string('amount')->toString(),
            $request->filled('reference') ? $request->string('reference')->trim()->toString() : null,
            $request->filled('note') ? $request->string('note')->trim()->toString() : null,
            $request->actor(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment received from :name.', ['name' => $customer->name])]);
        Inertia::flash('printReceipt', $entry->id);

        return back();
    }

    /**
     * 80 mm receipt for a due payment (FR-15.6).
     */
    public function receipt(LedgerEntry $entry): View
    {
        abort_unless($entry->type === LedgerType::Payment, 404);
        $entry->load(['creator:id,name']);
        $customer = Customer::query()->findOrFail($entry->customer_id);
        $after = 0;
        foreach (LedgerEntry::query()->where('customer_id', $customer->id)->where('id', '<=', $entry->id)->pluck('amount') as $a) {
            $after += Money::toPaisa((string) $a);
        }

        return view('print.receipt', [
            'entry' => $entry, 'customer' => $customer, 'settings' => Settings::all(),
            'before' => Money::fromPaisa($after - Money::toPaisa($entry->amount)), 'after' => Money::fromPaisa($after),
        ]);
    }

    /**
     * Printable customer statement (FR-15.8), A4.
     */
    public function statement(Customer $customer): View
    {
        $rows = [];
        $running = 0;
        foreach (LedgerEntry::query()->with('bill:id,number')->where('customer_id', $customer->id)->orderBy('id')->get() as $e) {
            $running += Money::toPaisa($e->amount);
            $rows[] = ['date' => $e->created_at, 'what' => $e->type->label(), 'ref' => $e->bill->number ?? $e->reference, 'note' => $e->note, 'amount' => $e->amount, 'balance' => Money::fromPaisa($running)];
        }

        return view('print.statement', ['customer' => $customer, 'rows' => $rows, 'summary' => DuesData::summary($customer), 'settings' => Settings::all()]);
    }

    public function move(MoveDueRequest $request, Customer $customer, PostLedger $ledger): RedirectResponse
    {
        $ledger->move(
            $customer,
            Customer::query()->findOrFail($request->integer('to_customer_id')),
            $request->string('amount')->toString(),
            $request->string('note')->trim()->toString(),
            $request->actor(),
            $request->filled('approver_username') ? $request->string('approver_username')->toString() : null,
            $request->filled('approver_secret') ? $request->string('approver_secret')->toString() : null,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Due moved.')]);

        return back();
    }

    public function storeGroup(CustomerGroupRequest $request): RedirectResponse
    {
        $group = CustomerGroup::query()->create(['name' => $request->string('name')->trim()->toString()]);
        Audit::record('customer.group_created', $group, ['name' => $group->name], $request->actor());

        return back();
    }

    /**
     * @return array{name: string, phone: string|null, customer_group_id: int|null, note: string|null, email: string|null, birthday: string|null, anniversary: string|null, preferred_seat: string|null}
     */
    private function details(CustomerRequest $request): array
    {
        return [
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->filled('phone') ? $request->string('phone')->toString() : null,
            'customer_group_id' => $request->filled('customer_group_id') ? $request->integer('customer_group_id') : null,
            'note' => $request->filled('note') ? $request->string('note')->trim()->toString() : null,
            'email' => $request->filled('email') ? $request->string('email')->trim()->lower()->toString() : null,
            'birthday' => $request->filled('birthday') ? $request->string('birthday')->toString() : null,
            'anniversary' => $request->filled('anniversary') ? $request->string('anniversary')->toString() : null,
            'preferred_seat' => $request->filled('preferred_seat') ? $request->string('preferred_seat')->trim()->toString() : null,
        ];
    }
}
