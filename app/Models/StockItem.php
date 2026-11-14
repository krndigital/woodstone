<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockKind;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * on_hand and avg_cost are only changed by App\Actions\Stock\StockLedger.
 *
 * @property int $id
 * @property string $name
 * @property StockKind $kind
 * @property string $unit
 * @property string|null $purchase_unit
 * @property string|null $purchase_factor
 * @property string $on_hand
 * @property string $avg_cost
 * @property string $reorder_level
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'kind', 'unit', 'purchase_unit', 'purchase_factor', 'reorder_level'])]
class StockItem extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => StockKind::class,
            'purchase_factor' => 'decimal:3',
            'on_hand' => 'decimal:3',
            'avg_cost' => 'decimal:4',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
