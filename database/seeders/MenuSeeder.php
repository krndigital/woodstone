<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Menu\LoadWoodstoneMenu;
use App\Enums\StationKind;
use App\Models\MenuItem;
use App\Models\Station;
use Illuminate\Database\Seeder;

/**
 * Local/testing: stations plus WoodStone's real menu with prices and photos.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Station::query()->firstOrCreate(['name' => 'Kitchen'], ['kind' => StationKind::Kitchen, 'sort_order' => 1, 'is_active' => true]);
        Station::query()->firstOrCreate(['name' => 'Bar'], ['kind' => StationKind::Bar, 'sort_order' => 2, 'is_active' => true]);
        Station::query()->firstOrCreate(['name' => 'Counter'], ['kind' => StationKind::Counter, 'sort_order' => 3, 'is_active' => true]);

        app(LoadWoodstoneMenu::class)->handle();

        MenuItem::query()->where('code', 'MOMO-01')->firstOrFail()->modifiers()->createMany([
            ['name' => 'Extra sauce', 'price' => '20', 'sort_order' => 0],
            ['name' => 'Extra pieces', 'price' => '60', 'sort_order' => 1],
        ]);
    }
}
