<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TableKind;
use App\Enums\TableStatus;
use Carbon\CarbonInterface;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A table or wooden cabin. Status is changed only through actions (never mass assignment).
 *
 * @property int $id
 * @property int $zone_id
 * @property string $code
 * @property TableKind $kind
 * @property int $seats
 * @property TableStatus $status
 * @property string|null $reserved_for
 * @property CarbonInterface|null $reserved_at
 * @property CarbonInterface|null $status_changed_at
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['zone_id', 'code', 'kind', 'seats', 'sort_order', 'is_active'])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => TableKind::class,
            'status' => TableStatus::class,
            'seats' => 'integer',
            'reserved_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function label(): string
    {
        return $this->kind === TableKind::Cabin ? 'Cabin '.ltrim($this->code, 'Cc') : 'Table '.$this->code;
    }

    public function setStatus(TableStatus $status): void
    {
        $this->forceFill([
            'status' => $status,
            'status_changed_at' => now(),
            'reserved_for' => $status === TableStatus::Reserved ? $this->reserved_for : null,
            'reserved_at' => $status === TableStatus::Reserved ? $this->reserved_at : null,
        ])->save();
    }
}
