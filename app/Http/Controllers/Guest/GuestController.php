<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\GuestCallRequest;
use App\Models\DiningTable;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ServiceCall;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public QR page for a seat (FR-5.2, M16): live menu and "call waiter" buttons. No staff data,
 * no prices that are not on the menu, nothing the guest can change except creating a call.
 */
class GuestController extends Controller
{
    public function show(string $token): Response
    {
        $table = $this->table($token);

        $categories = MenuCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'parent_id']);
        $items = MenuItem::query()->where('is_active', true)->where('show_on_qr', true)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (MenuItem $m): array => [
                'id' => $m->id, 'name' => $m->name, 'description' => $m->description, 'category_id' => $m->category_id,
                'price' => $m->price, 'is_veg' => $m->is_veg, 'available' => $m->isOrderableAt(now()), 'image_url' => $m->imageUrl(),
            ])->values()->all();

        return Inertia::render('guest/Table', [
            'token' => $token,
            'table' => ['label' => $table->label(), 'can_call' => $this->canCall($table)],
            'restaurant' => ['name' => Settings::string('restaurant_name'), 'phone' => Settings::get('phone')],
            'categories' => $categories->map(fn (MenuCategory $c): array => ['id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id])->values()->all(),
            'items' => $items,
            'callTypes' => array_map(fn (string $k): array => ['value' => $k, 'label' => ServiceCall::TYPES[$k]], ['waiter', 'bill', 'water', 'help']),
        ]);
    }

    public function call(GuestCallRequest $request, string $token): RedirectResponse
    {
        $table = $this->table($token);

        if (! $this->canCall($table)) {
            throw ValidationException::withMessages(['type' => __('Please ask a staff member to open your table first.')]);
        }
        if (ServiceCall::query()->where('dining_table_id', $table->id)->where('created_at', '>=', now()->subSeconds(60))->exists()) {
            throw ValidationException::withMessages(['type' => __('Your request was sent. Please wait a minute before calling again.')]);
        }

        $call = new ServiceCall;
        $call->forceFill([
            'dining_table_id' => $table->id,
            'order_id' => Order::query()->where('dining_table_id', $table->id)->where('status', OrderStatus::Open->value)->value('id'),
            'type' => $request->string('type')->toString(),
            'note' => $request->filled('note') ? $request->string('note')->trim()->limit(120, '')->toString() : null,
            'status' => 'sent',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'public_id' => (string) Str::ulid(),
        ])->save();

        Inertia::flash('call', $call->public_id);

        return back();
    }

    /**
     * Live status for the guest: sent → waiter coming (first name) → done (FR-16.3).
     */
    public function status(string $token, string $publicId): JsonResponse
    {
        $table = $this->table($token);
        $call = ServiceCall::query()->with('acceptor:id,name')->where('dining_table_id', $table->id)->where('public_id', $publicId)->firstOrFail();

        return response()->json([
            'status' => $call->status,
            'type' => $call->typeLabel(),
            'waiter' => $call->acceptor !== null ? Str::before($call->acceptor->name, ' ') : null,
        ]);
    }

    private function table(string $token): DiningTable
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token) === 1, 404);

        return DiningTable::query()->where('qr_token', $token)->where('is_active', true)->firstOrFail();
    }

    private function canCall(DiningTable $table): bool
    {
        return in_array($table->status, [TableStatus::Occupied, TableStatus::BillAsked], true) || Settings::bool('guest_call_any_status');
    }
}
