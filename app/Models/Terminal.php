<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A registered shared device. Only a SHA-256 hash of its secret token is stored;
 * the plain token lives in an encrypted, http-only cookie on that device.
 *
 * @property int $id
 * @property string $name
 * @property string $token_hash
 * @property int|null $registered_by
 * @property CarbonInterface|null $last_seen_at
 * @property string|null $last_ip
 * @property CarbonInterface|null $revoked_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name'])]
#[Hidden(['token_hash'])]
class Terminal extends Model
{
    public const COOKIE = 'ws_terminal';

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * @param  Builder<Terminal>  $query
     * @return Builder<Terminal>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
