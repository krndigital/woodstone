<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $code
 * @property int $category_id
 * @property int $station_id
 * @property string $name
 * @property string|null $description
 * @property string $price
 * @property string|null $cost
 * @property bool $is_veg
 * @property bool $vat_applicable
 * @property bool $show_on_qr
 * @property bool $is_available
 * @property bool $is_active
 * @property string|null $available_from
 * @property string|null $available_to
 * @property string|null $image_path
 * @property int $sort_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'code', 'category_id', 'station_id', 'name', 'description', 'price', 'cost',
    'is_veg', 'vat_applicable', 'show_on_qr', 'available_from', 'available_to', 'sort_order',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'is_veg' => 'boolean',
            'vat_applicable' => 'boolean',
            'show_on_qr' => 'boolean',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Station, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * @return HasMany<MenuItemModifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(MenuItemModifier::class)->orderBy('sort_order');
    }

    public function marginPercent(): ?int
    {
        return Money::marginPercent($this->price, $this->cost);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : Storage::disk('public')->url($this->image_path);
    }

    /**
     * Whether the item can be ordered at the given time (sold-out flag + optional time window).
     * Windows that cross midnight (e.g. 18:00–02:00) are supported.
     */
    public function isOrderableAt(CarbonInterface $at): bool
    {
        if (! $this->is_active || ! $this->is_available) {
            return false;
        }

        if ($this->available_from === null || $this->available_to === null) {
            return true;
        }

        $now = $at->format('H:i:s');
        $from = substr($this->available_from, 0, 8);
        $to = substr($this->available_to, 0, 8);

        return $from <= $to ? ($now >= $from && $now <= $to) : ($now >= $from || $now <= $to);
    }
}
