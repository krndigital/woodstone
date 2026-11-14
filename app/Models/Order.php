<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nothing is mass assignable: orders change only through App\Actions\Pos.
 *
 * @property int $id
 * @property string $number
 * @property OrderType $type
 * @property int|null $dining_table_id
 * @property int $guests
 * @property OrderStatus $status
 * @property int $waiter_id
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $delivery_address
 * @property string|null $note
 * @property string $subtotal
 * @property string|null $cancel_reason
 * @property CarbonInterface $opened_at
 * @property CarbonInterface|null $closed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read DiningTable|null $diningTable
 * @property-read User $waiter
 */
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'guests' => 'integer',
            'subtotal' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<Kot, $this>
     */
    public function kots(): HasMany
    {
        return $this->hasMany(Kot::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === OrderStatus::Open;
    }

    public function label(): string
    {
        return $this->diningTable !== null ? $this->diningTable->label() : $this->type->label().' #'.$this->number;
    }

    /**
     * Recomputes the subtotal from non-void items (server is the only source of totals).
     */
    public function recalculate(): void
    {
        $paisa = 0;
        foreach ($this->items()->where('status', '!=', OrderItemStatus::Void->value)->pluck('line_total') as $line) {
            $paisa += Money::toPaisa((string) $line);
        }

        $this->forceFill(['subtotal' => Money::fromPaisa($paisa)])->save();
    }
}
