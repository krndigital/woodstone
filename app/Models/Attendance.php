<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $staff_id
 * @property CarbonInterface $work_date
 * @property CarbonInterface|null $check_in_at
 * @property CarbonInterface|null $check_out_at
 * @property string $status
 * @property string|null $note
 * @property int|null $recorded_by
 * @property-read Staff $staff
 */
class Attendance extends Model
{
    protected function casts(): array
    {
        return ['work_date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
