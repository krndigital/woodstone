<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $staff_id
 * @property string $amount
 * @property int $account_id
 * @property CarbonInterface $given_on
 * @property string|null $note
 * @property int|null $payslip_id
 * @property int $created_by
 * @property-read Staff $staff
 */
class StaffAdvance extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'given_on' => 'date', 'payslip_id' => 'integer'];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
