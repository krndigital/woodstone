<?php

declare(strict_types=1);

namespace App\Actions\Floor;

use App\Actions\Pos\OpenOrder;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Audit;
use App\Support\CashBook;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reservations with an advance (FR-1.4). The advance is money held for the guest (a liability)
 * until it is used on the bill, refunded, or kept when the guest does not come.
 */
final class Reservations
{
    public function __construct(private readonly OpenOrder $openOrder) {}

    /**
     * @param  array{dining_table_id: int, name: string, phone: string|null, reserved_at: string, guests: int, advance: string, advance_method: string|null, note: string|null}  $d
     */
    public function create(array $d, User $by): Reservation
    {
        $table = DiningTable::query()->whereKey($d['dining_table_id'])->where('is_active', true)->firstOrFail();
        $at = CarbonImmutable::parse($d['reserved_at']);
        $clash = Reservation::query()->where('dining_table_id', $table->id)->where('status', 'booked')
            ->whereBetween('reserved_at', [$at->subHours(2)->toDateTimeString(), $at->addHours(2)->toDateTimeString()])->exists();
        if ($clash) {
            throw ValidationException::withMessages(['reserved_at' => __(':table is already booked within 2 hours of that time.', ['table' => $table->label()])]);
        }
        $advance = Money::toPaisa($d['advance'] === '' ? '0' : $d['advance']);
        if ($advance > 0 && $d['advance_method'] === null) {
            throw ValidationException::withMessages(['advance_method' => __('How was the advance paid?')]);
        }

        return DB::transaction(function () use ($d, $table, $at, $advance, $by): Reservation {
            $r = new Reservation;
            $r->forceFill([
                'dining_table_id' => $table->id, 'name' => $d['name'], 'phone' => $d['phone'], 'reserved_at' => $at,
                'guests' => $d['guests'], 'advance' => Money::fromPaisa($advance), 'advance_used' => '0', 'status' => 'booked',
                'note' => $d['note'], 'created_by' => $by->id,
            ])->save();
            if ($advance > 0 && $d['advance_method'] !== null) {
                CashBook::post(CashBook::accountForMethod($d['advance_method']), $advance, 'reservation', $r->id, $by, 'Advance · '.$r->name.' · '.$table->code);
            }
            if ($at->isToday() && $table->status === TableStatus::Free) {
                $table->forceFill(['reserved_for' => $r->name.' '.$at->format('H:i')])->save();
                $table->setStatus(TableStatus::Reserved);
            }
            Audit::record('reservation.created', $r, ['table' => $table->code, 'at' => $at->toDateTimeString(), 'advance' => $r->advance], $by);

            return $r;
        });
    }

    public function seat(Reservation $r, User $by): Order
    {
        if ($r->status !== 'booked') {
            throw ValidationException::withMessages(['reservation' => __('This booking is :status.', ['status' => $r->status])]);
        }

        return DB::transaction(function () use ($r, $by): Order {
            $order = $this->openOrder->handle($by, OrderType::DineIn, $r->dining_table_id, ['guests' => $r->guests, 'customer_name' => $r->name, 'customer_phone' => $r->phone, 'note' => $r->note]);
            $r->forceFill(['status' => 'seated', 'order_id' => $order->id])->save();
            Audit::record('reservation.seated', $r, ['order' => $order->number], $by);

            return $order;
        });
    }

    /**
     * Cancel or mark no-show. The unused advance is refunded or kept as other income.
     */
    public function close(Reservation $r, string $status, ?string $refundMethod, User $by): void
    {
        if ($r->status !== 'booked') {
            throw ValidationException::withMessages(['reservation' => __('This booking is :status.', ['status' => $r->status])]);
        }

        DB::transaction(function () use ($r, $status, $refundMethod, $by): void {
            $left = Money::toPaisa($r->advance) - Money::toPaisa($r->advance_used);
            if ($left > 0 && $refundMethod !== null) {
                CashBook::post(CashBook::accountForMethod($refundMethod), -$left, 'reservation', $r->id, $by, 'Advance refunded · '.$r->name);
                $r->forceFill(['advance_used' => $r->advance])->save();
            }
            $r->forceFill(['status' => $status])->save();
            $table = $r->diningTable;
            if ($table->status === TableStatus::Reserved) {
                $table->setStatus(TableStatus::Free);
            }
            Audit::record('reservation.'.$status, $r, ['refunded' => $refundMethod !== null ? Money::fromPaisa($left) : '0.00', 'kept' => $refundMethod === null ? Money::fromPaisa($left) : '0.00'], $by);
        });
    }
}
