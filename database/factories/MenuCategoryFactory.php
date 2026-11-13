<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => 'Category '.fake()->unique()->numberBetween(1, 99999),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
