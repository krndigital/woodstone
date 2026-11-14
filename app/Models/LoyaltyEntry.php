<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only loyalty points row.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $points
 * @property string $type
 * @property int|null $bill_id
 * @property CarbonInterface|null $expires_on
 * @property string|null $note
 * @property int $created_by
 * @property CarbonInterface $created_at
 */
class LoyaltyEntry extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Loyalty entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Loyalty entries are append-only.'));
    }

    protected function casts(): array
    {
        return ['points' => 'integer', 'expires_on' => 'date', 'created_at' => 'datetime'];
    }
}
