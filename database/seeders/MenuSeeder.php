<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StationKind;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Station;
use Illuminate\Database\Seeder;

/**
 * Demo menu matching the approved design (local/testing only).
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $kitchen = Station::query()->create(['name' => 'Kitchen', 'kind' => StationKind::Kitchen, 'sort_order' => 1, 'is_active' => true]);
        $bar = Station::query()->create(['name' => 'Bar', 'kind' => StationKind::Bar, 'sort_order' => 2, 'is_active' => true]);
        $counter = Station::query()->create(['name' => 'Counter', 'kind' => StationKind::Counter, 'sort_order' => 3, 'is_active' => true]);

        $categories = [];
        foreach (['Momo', 'Noodles & Rice', 'Snacks', 'Drinks', 'Beer & Spirits', 'Hookah & Smoke'] as $i => $name) {
            $categories[$name] = MenuCategory::query()->create(['name' => $name, 'sort_order' => $i, 'is_active' => true]);
        }

        // code, category, name, price, cost, veg, station
        $items = [
            ['MOMO-01', 'Momo', 'Chicken Momo (Steam)', '220', '88', false, $kitchen],
            ['MOMO-02', 'Momo', 'Chicken Momo (Fried)', '250', '98', false, $kitchen],
            ['MOMO-03', 'Momo', 'Chicken Jhol Momo', '260', '102', false, $kitchen],
            ['MOMO-04', 'Momo', 'Veg Momo', '170', '55', true, $kitchen],
            ['NR-01', 'Noodles & Rice', 'Chicken Chowmein', '200', '78', false, $kitchen],
            ['NR-02', 'Noodles & Rice', 'Chicken Fried Rice', '240', '92', false, $kitchen],
            ['NR-03', 'Noodles & Rice', 'Chicken Thakali Set', '450', '190', false, $kitchen],
            ['NR-04', 'Noodles & Rice', 'Veg Chowmein', '160', '50', true, $kitchen],
            ['SN-01', 'Snacks', 'French Fries', '180', '52', true, $kitchen],
            ['SN-02', 'Snacks', 'Chicken Chilli', '380', '150', false, $kitchen],
            ['SN-03', 'Snacks', 'Buff Sukuti', '350', '140', false, $kitchen],
            ['SN-04', 'Snacks', 'Chicken Wings (6)', '420', '175', false, $kitchen],
            ['SN-05', 'Snacks', 'Chicken Sekuwa', '400', '160', false, $kitchen],
            ['SN-06', 'Snacks', 'Peanut Sadeko', '180', '55', true, $kitchen],
            ['DR-01', 'Drinks', 'Milk Tea', '50', '15', true, $bar],
            ['DR-02', 'Drinks', 'Milk Coffee', '130', '40', true, $bar],
            ['DR-03', 'Drinks', 'Coke 250 ml', '80', '48', true, $bar],
            ['DR-04', 'Drinks', 'Sweet Lassi', '150', '50', true, $bar],
            ['DR-05', 'Drinks', 'Mineral Water 1 L', '50', '25', true, $bar],
            ['BS-01', 'Beer & Spirits', 'Gorkha Beer 650 ml', '600', '390', true, $bar],
            ['BS-02', 'Beer & Spirits', 'Tuborg 650 ml', '650', '420', true, $bar],
            ['BS-03', 'Beer & Spirits', 'Khukri Rum (peg)', '220', '90', true, $bar],
            ['HK-01', 'Hookah & Smoke', 'Hookah', '600', '180', true, $bar],
            ['HK-02', 'Hookah & Smoke', 'Shikhar Ice (stick)', '30', '22', true, $counter],
        ];

        foreach ($items as $i => [$code, $category, $name, $price, $cost, $veg, $station]) {
            $item = new MenuItem([
                'code' => $code,
                'category_id' => $categories[$category]->id,
                'station_id' => $station->id,
                'name' => $name,
                'price' => $price,
                'cost' => $cost,
                'is_veg' => $veg,
                'vat_applicable' => true,
                'show_on_qr' => $category !== 'Hookah & Smoke',
                'sort_order' => $i,
            ]);
            $item->save();
        }

        MenuItem::query()->where('code', 'MOMO-01')->firstOrFail()->modifiers()->createMany([
            ['name' => 'Extra sauce', 'price' => '20', 'sort_order' => 0],
            ['name' => 'Half plate', 'price' => '0', 'sort_order' => 1],
        ]);
    }
}
