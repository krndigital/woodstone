<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $citizenship_no
 * @property string $position
 * @property CarbonInterface $joined_on
 * @property string $salary_type
 * @property string $salary_amount
 * @property int|null $weekly_off
 * @property string|null $shift_start
 * @property string|null $shift_end
 * @property string|null $attendance_pin
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['name', 'phone', 'address', 'citizenship_no', 'position', 'joined_on', 'weekly_off', 'shift_start', 'shift_end'])]
#[Hidden(['citizenship_no', 'attendance_pin'])]
class Staff extends Model
{
    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'citizenship_no' => 'encrypted',
            'joined_on' => 'date',
            'salary_amount' => 'decimal:2',
            'weekly_off' => 'integer',
            'attendance_pin' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
