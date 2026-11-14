<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $staff_id
 * @property string $month
 * @property int $working_days
 * @property int $present_days
 * @property int $late_days
 * @property int $leave_days
 * @property int $absent_days
 * @property string $base_salary
 * @property string $earned
 * @property string $advance_deduction
 * @property string $other_deduction
 * @property string $bonus
 * @property string $net
 * @property string $status
 * @property int|null $expense_id
 * @property CarbonInterface|null $paid_at
 * @property-read Staff $staff
 */
class Payslip extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Payslip $p): void {
            if ($p->getOriginal('status') === 'paid') {
                throw new LogicException('A paid payslip cannot be changed.');
            }
        });
        static::deleting(function (Payslip $p): void {
            if ($p->status === 'paid') {
                throw new LogicException('A paid payslip cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2', 'earned' => 'decimal:2', 'advance_deduction' => 'decimal:2',
            'other_deduction' => 'decimal:2', 'bonus' => 'decimal:2', 'net' => 'decimal:2', 'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
