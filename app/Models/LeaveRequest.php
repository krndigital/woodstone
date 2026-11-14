<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $staff_id
 * @property CarbonInterface $from_date
 * @property CarbonInterface $to_date
 * @property string $reason
 * @property string $status
 * @property int|null $decided_by
 * @property CarbonInterface|null $decided_at
 * @property-read Staff $staff
 */
class LeaveRequest extends Model
{
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'decided_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
