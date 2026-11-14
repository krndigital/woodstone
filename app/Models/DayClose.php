<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property CarbonInterface $business_date
 * @property string $opening_cash
 * @property int $opened_by
 * @property CarbonInterface $opened_at
 * @property string|null $expected_cash
 * @property string|null $counted_cash
 * @property string|null $difference
 * @property string|null $note
 * @property int|null $closed_by
 * @property CarbonInterface|null $closed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $opener
 * @property-read User|null $closer
 */
class DayClose extends Model
{
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_cash' => 'decimal:2',
            'opened_at' => 'datetime',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
