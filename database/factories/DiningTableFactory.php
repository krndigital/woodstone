<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TableKind;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'code' => 'T'.fake()->unique()->numberBetween(1, 99999),
            'kind' => TableKind::Table,
            'seats' => 4,
            'status' => TableStatus::Free,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function status(TableStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
