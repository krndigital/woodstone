<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'IT-'.fake()->unique()->numberBetween(1000, 999999),
            'category_id' => MenuCategory::factory(),
            'station_id' => Station::factory(),
            'name' => fake()->words(2, true),
            'description' => null,
            'price' => '220.00',
            'cost' => '88.00',
            'is_veg' => false,
            'vat_applicable' => true,
            'show_on_qr' => true,
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => ['is_available' => false]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
