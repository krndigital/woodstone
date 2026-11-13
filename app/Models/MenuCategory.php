<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MenuCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['parent_id', 'name', 'sort_order', 'is_active'])]
class MenuCategory extends Model
{
    /** @use HasFactory<MenuCategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category_id');
    }
}
