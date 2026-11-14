<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Credit limit and active flag are not mass assignable (set by actions with permission checks).
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property int|null $customer_group_id
 * @property string $credit_limit
 * @property string|null $note
 * @property string|null $email
 * @property CarbonInterface|null $birthday
 * @property CarbonInterface|null $anniversary
 * @property string|null $preferred_seat
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read CustomerGroup|null $group
 */
#[Fillable(['name', 'phone', 'customer_group_id', 'note', 'email', 'birthday', 'anniversary', 'preferred_seat'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'is_active' => 'boolean', 'birthday' => 'date', 'anniversary' => 'date'];
    }

    /**
     * @return BelongsTo<CustomerGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Current due in paisa, always summed from the ledger.
     */
    public function balancePaisa(): int
    {
        $sum = 0;
        foreach ($this->ledger()->pluck('amount') as $amount) {
            $sum += Money::toPaisa((string) $amount);
        }

        return $sum;
    }
}
