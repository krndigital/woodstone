<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dining_table_id
 * @property int|null $order_id
 * @property string $type
 * @property string|null $note
 * @property string $status
 * @property int|null $accepted_by
 * @property CarbonInterface|null $accepted_at
 * @property CarbonInterface|null $done_at
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $public_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read DiningTable $diningTable
 * @property-read User|null $acceptor
 */
class ServiceCall extends Model
{
    public const TYPES = ['waiter' => 'Call waiter', 'bill' => 'Request bill', 'water' => 'Water', 'help' => 'Order help', 'ping' => 'Food ready'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'done_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
