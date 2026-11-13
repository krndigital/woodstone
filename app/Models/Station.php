<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StationKind;
use Carbon\CarbonInterface;
use Database\Factories\StationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property StationKind $kind
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'kind', 'sort_order', 'is_active'])]
class Station extends Model
{
    /** @use HasFactory<StationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => StationKind::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
