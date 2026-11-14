<?php

declare(strict_types=1);

namespace App\Http\Controllers\Floor;

use App\Actions\Floor\Reservations;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Floor\ReservationRequest;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use App\Support\BsDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Table and cabin bookings (FR-1.4). Behind `can:floor.view`; money parts need billing permission.
 */
class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('floor/Reservations', [
            'reservations' => Reservation::query()->with('diningTable')
                ->where(fn ($q) => $q->where('status', 'booked')->orWhere('reserved_at', '>=', now()->subDays(2)))
                ->orderBy('reserved_at')->limit(200)->get()
                ->map(fn (Reservation $r): array => [
                    'id' => $r->id, 'table' => $r->diningTable->label(), 'name' => $r->name, 'phone' => $r->phone,
                    'reserved_at' => $r->reserved_at->format('Y-m-d H:i'), 'bs' => BsDate::long($r->reserved_at),
                    'guests' => $r->guests, 'advance' => $r->advance, 'advance_used' => $r->advance_used, 'status' => $r->status,
                    'order_id' => $r->order_id, 'note' => $r->note,
                ])->values()->all(),
            'tables' => DiningTable::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get()
                ->map(fn (DiningTable $t): array => ['id' => $t->id, 'label' => $t->label(), 'seats' => $t->seats])->values()->all(),
            'can' => ['money' => $user->hasPermission(Permission::TakePayment)],
        ]);
    }

    public function store(ReservationRequest $request, Reservations $reservations): RedirectResponse
    {
        $reservations->create([
            'dining_table_id' => $request->integer('dining_table_id'),
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->optionalString('phone'),
            'reserved_at' => $request->string('reserved_at')->toString(),
            'guests' => $request->integer('guests'),
            'advance' => $request->filled('advance') ? $request->string('advance')->toString() : '0',
            'advance_method' => $request->optionalString('advance_method'),
            'note' => $request->optionalString('note'),
        ], $request->actor());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking saved.')]);

        return back();
    }

    public function seat(Request $request, Reservation $reservation, Reservations $reservations): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::UsePos), 403);
        $order = $reservations->seat($reservation, $user);

        return to_route('pos.orders.show', $order);
    }

    public function close(ReservationRequest $request, Reservation $reservation, Reservations $reservations): RedirectResponse
    {
        $reservations->close($reservation, $request->string('status')->toString(), $request->optionalString('refund_method'), $request->actor());

        return back();
    }
}
