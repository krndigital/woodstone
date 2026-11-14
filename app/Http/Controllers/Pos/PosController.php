<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Actions\Pos\AddOrderItem;
use App\Actions\Pos\CancelOrder;
use App\Actions\Pos\EnsureOrderOpen;
use App\Actions\Pos\MergeOrders;
use App\Actions\Pos\MoveOrder;
use App\Actions\Pos\OpenOrder;
use App\Actions\Pos\SendKot;
use App\Actions\Pos\VoidOrderItem;
use App\Enums\OrderItemStatus;
use App\Enums\OrderType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\AddItemRequest;
use App\Http\Requests\Pos\CancelOrderRequest;
use App\Http\Requests\Pos\MergeOrderRequest;
use App\Http\Requests\Pos\MoveOrderRequest;
use App\Http\Requests\Pos\OpenOrderRequest;
use App\Http\Requests\Pos\SimplePosRequest;
use App\Http\Requests\Pos\UpdateItemRequest;
use App\Http\Requests\Pos\VoidItemRequest;
use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Audit;
use App\Support\FloorData;
use App\Support\ItemEvents;
use App\Support\ManagerApproval;
use App\Support\Money;
use App\Support\OrderData;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * POS orders and KOT (step 5, FR-2). Every route is behind `can:pos.use`.
 */
class PosController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('pos/Index', [
            'zones' => FloorData::zones(),
            'tables' => FloorData::tables(),
            'orders' => OrderData::openOrders(),
            'can' => ['pay' => $user->hasPermission(Permission::TakePayment)],
        ]);
    }

    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $order->isOpen()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Order #:number is closed.', ['number' => $order->number])]);

            return to_route('pos.index');
        }

        return Inertia::render('pos/Order', [
            'order' => OrderData::order($order),
            'menu' => OrderData::menu(),
            'freeTables' => array_values(array_filter(FloorData::tables(), fn (array $t): bool => in_array($t['status'], ['free', 'reserved'], true))),
            'otherOrders' => array_values(array_filter(OrderData::openOrders(), fn (array $o): bool => $o['id'] !== $order->id)),
            'can' => [
                'pay' => $user->hasPermission(Permission::TakePayment),
                'approve' => $user->hasPermission(Permission::ApproveOverride),
            ],
            'restaurant' => Settings::string('restaurant_name'),
        ]);
    }

    public function store(OpenOrderRequest $request, OpenOrder $action): RedirectResponse
    {
        $order = $action->handle(
            $request->actor(),
            OrderType::from($request->string('type')->toString()),
            $request->filled('dining_table_id') ? $request->integer('dining_table_id') : null,
            [
                'guests' => $request->integer('guests', 1),
                'customer_name' => $request->filled('customer_name') ? $request->string('customer_name')->trim()->toString() : null,
                'customer_phone' => $request->filled('customer_phone') ? $request->string('customer_phone')->trim()->toString() : null,
                'delivery_address' => $request->filled('delivery_address') ? $request->string('delivery_address')->trim()->toString() : null,
                'note' => $request->filled('note') ? $request->string('note')->trim()->toString() : null,
            ],
        );

        return to_route('pos.orders.show', $order);
    }

    public function addItem(AddItemRequest $request, Order $order, AddOrderItem $action): RedirectResponse
    {
        /** @var list<int> $modifierIds */
        $modifierIds = array_values(array_map('intval', (array) $request->input('modifier_ids', [])));

        $action->handle(
            $order,
            MenuItem::query()->findOrFail($request->integer('menu_item_id')),
            $request->integer('quantity'),
            $modifierIds,
            $request->filled('note') ? $request->string('note')->trim()->toString() : null,
            $request->actor(),
        );

        return back();
    }

    public function updateItem(UpdateItemRequest $request, OrderItem $orderItem): RedirectResponse
    {
        $this->ensureDraft($orderItem);

        $quantity = $request->integer('quantity');
        $orderItem->forceFill([
            'quantity' => $quantity,
            'line_total' => Money::fromPaisa(Money::toPaisa($orderItem->unit_price) * $quantity),
            'note' => $request->filled('note') ? $request->string('note')->trim()->toString() : null,
        ])->save();
        $orderItem->order->recalculate();

        return back();
    }

    public function removeItem(SimplePosRequest $request, OrderItem $orderItem): RedirectResponse
    {
        $this->ensureDraft($orderItem);

        $order = $orderItem->order;
        $orderItem->delete();
        $order->recalculate();

        return back();
    }

    public function sendKot(SimplePosRequest $request, Order $order, SendKot $action): RedirectResponse
    {
        $kots = $action->handle($order, $request->actor());

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} KOT sent.|[2,*] :count KOTs sent.', count($kots), ['count' => count($kots)])]);
        Inertia::flash('printKots', array_map(fn (Kot $k): int => $k->id, $kots));

        return back();
    }

    public function voidItem(VoidItemRequest $request, OrderItem $orderItem, VoidOrderItem $action): RedirectResponse
    {
        $actor = $request->actor();
        EnsureOrderOpen::check($orderItem->order);

        $approver = ManagerApproval::resolve(
            $actor,
            $request->input('approver_username') === null ? null : $request->string('approver_username')->toString(),
            $request->input('approver_secret') === null ? null : $request->string('approver_secret')->toString(),
        );

        $action->handle($orderItem, $request->string('reason')->trim()->toString(), $actor, $approver);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':item voided.', ['item' => $orderItem->name])]);

        return back();
    }

    public function markServed(SimplePosRequest $request, OrderItem $orderItem): RedirectResponse
    {
        EnsureOrderOpen::check($orderItem->order);

        if (! in_array($orderItem->status, OrderItemStatus::inKitchen(), true)) {
            throw ValidationException::withMessages(['order' => __('This item is not waiting to be served.')]);
        }

        $orderItem->forceFill(['status' => OrderItemStatus::Served, 'served_at' => now()])->save();
        ItemEvents::log([$orderItem->id], 'served', $request->actor()->id);

        return back();
    }

    public function move(MoveOrderRequest $request, Order $order, MoveOrder $action): RedirectResponse
    {
        $action->handle($order, $request->integer('dining_table_id'), $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Moved to :table.', ['table' => $order->refresh()->label()])]);

        return back();
    }

    public function merge(MergeOrderRequest $request, Order $order, MergeOrders $action): RedirectResponse
    {
        $action->handle($order, Order::query()->findOrFail($request->integer('source_order_id')), $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Orders merged.')]);

        return back();
    }

    public function cancel(CancelOrderRequest $request, Order $order, CancelOrder $action): RedirectResponse
    {
        $action->handle($order, $request->string('reason')->trim()->toString(), $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order #:number cancelled.', ['number' => $order->number])]);

        return to_route('pos.index');
    }

    /**
     * 80 mm KOT slip. The first print is marked; later prints say REPRINT.
     */
    public function printKot(Request $request, Kot $kot): View
    {
        $kot->load(['order.diningTable', 'station', 'creator:id,name', 'items']);
        $reprint = $kot->printed_at !== null;

        if (! $reprint) {
            $kot->forceFill(['printed_at' => now()])->save();
        } else {
            Audit::record('order.kot_reprinted', $kot, ['number' => $kot->number]);
        }

        return view('print.kot', ['kot' => $kot, 'reprint' => $reprint]);
    }

    private function ensureDraft(OrderItem $orderItem): void
    {
        EnsureOrderOpen::check($orderItem->order);

        if ($orderItem->status !== OrderItemStatus::Draft) {
            throw ValidationException::withMessages(['quantity' => __('This item was already sent to the kitchen. Void it instead.')]);
        }
    }
}
