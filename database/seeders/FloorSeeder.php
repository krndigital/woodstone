<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TableKind;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Zone;
use Illuminate\Database\Seeder;

/**
 * Woodstone floor: garden tables, indoor tables and wooden cabins C1–C4.
 */
class FloorSeeder extends Seeder
{
    public function run(): void
    {
        $layout = [
            ['Garden', 'G', 8, TableKind::Table, [4, 4, 6, 4, 2, 8, 4, 4]],
            ['Indoor', 'T', 6, TableKind::Table, [2, 4, 4, 6, 2, 4]],
            ['Cabins', 'C', 4, TableKind::Cabin, [6, 6, 8, 6]],
        ];

        foreach ($layout as $z => [$zoneName, $prefix, $count, $kind, $seats]) {
            $zone = Zone::query()->create(['name' => $zoneName, 'sort_order' => $z, 'is_active' => true]);

            for ($i = 1; $i <= $count; $i++) {
                $table = new DiningTable(['zone_id' => $zone->id, 'code' => $prefix.$i, 'kind' => $kind, 'seats' => $seats[$i - 1], 'sort_order' => $i]);
                $table->forceFill(['status' => TableStatus::Free, 'is_active' => true])->save();
            }
        }
    }
}
