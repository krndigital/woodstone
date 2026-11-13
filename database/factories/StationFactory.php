<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StationKind;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 */
class StationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Station '.fake()->unique()->numberBetween(1, 99999),
            'kind' => StationKind::Kitchen,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
