<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dining_table_id
 * @property string $name
 * @property string|null $phone
 * @property CarbonInterface $reserved_at
 * @property int $guests
 * @property string $advance
 * @property string $advance_used
 * @property string $status
 * @property int|null $order_id
 * @property string|null $note
 * @property int $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read DiningTable $diningTable
 */
class Reservation extends Model
{
    protected function casts(): array
    {
        return ['reserved_at' => 'datetime', 'guests' => 'integer', 'advance' => 'decimal:2', 'advance_used' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }
}
