<?php

declare(strict_types=1);

namespace App\Actions\Menu;

use App\Enums\StationKind;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Station;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Loads WoodStone's own menu (database/data/woodstone-menu.php) with prices and photos (FR-3.1).
 * Safe to run again: items are matched by code, names and prices updated, price changes audited.
 * Photos the staff uploaded themselves are never replaced. Nothing is deleted; with
 * $archiveOthers, items not on the list are archived (old bills keep their names).
 */
final class LoadWoodstoneMenu
{
    public const PHOTO_DIR = 'menu/woodstone';

    /**
     * @return array{created: int, updated: int, archived: int}
     */
    public function handle(?User $by = null, bool $archiveOthers = false): array
    {
        /** @var list<array{name: string, station: string, photo: string, show_on_qr: bool, items: list<array{0: string, 1: string, 2: string, 3: bool, 4: string|null}>}> $menu */
        $menu = require database_path('data/woodstone-menu.php');
        $counts = ['created' => 0, 'updated' => 0, 'archived' => 0];

        DB::transaction(function () use ($menu, $by, $archiveOthers, &$counts): void {
            $codes = [];
            $order = 0;
            foreach ($menu as $c => $section) {
                $category = MenuCategory::query()->whereNull('parent_id')->where('name', $section['name'])->first()
                    ?? new MenuCategory(['name' => $section['name']]);
                $category->forceFill(['sort_order' => $c, 'is_active' => true])->save();
                $station = $this->station(StationKind::from($section['station']));

                foreach ($section['items'] as [$code, $name, $price, $veg, $photo]) {
                    $codes[] = $code;
                    $item = MenuItem::query()->where('code', $code)->first();
                    $isNew = $item === null;
                    $item ??= new MenuItem(['code' => $code, 'vat_applicable' => true]);
                    $oldPrice = $isNew ? null : Money::fromPaisa(Money::toPaisa($item->price));
                    $newPrice = Money::fromPaisa(Money::toPaisa($price));

                    $item->fill([
                        'category_id' => $category->id,
                        'station_id' => $station->id,
                        'name' => $name,
                        'price' => $newPrice,
                        'is_veg' => $veg,
                        'show_on_qr' => $section['show_on_qr'],
                        'sort_order' => $order++,
                    ]);
                    if ($item->image_path === null || str_starts_with($item->image_path, self::PHOTO_DIR.'/')) {
                        $item->forceFill(['image_path' => $this->photo($photo ?? $section['photo'])]);
                    }
                    $item->forceFill(['is_active' => true]);
                    $dirty = $item->isDirty();
                    $item->save();

                    if ($isNew) {
                        $counts['created']++;
                    } elseif ($dirty) {
                        $counts['updated']++;
                    }
                    if ($oldPrice !== null && $oldPrice !== $newPrice) {
                        Audit::record('menu.price_changed', $item, ['old' => $oldPrice, 'new' => $newPrice], $by);
                    }
                }
            }

            if ($archiveOthers) {
                $counts['archived'] = MenuItem::query()->where('is_active', true)->whereNotIn('code', $codes)->update(['is_active' => false]);
            }

            Audit::record('menu.preset_loaded', null, $counts + ['archive_others' => $archiveOthers], $by);
        });

        return $counts;
    }

    private function station(StationKind $kind): Station
    {
        return Station::query()->where('kind', $kind->value)->where('is_active', true)->orderBy('sort_order')->first()
            ?? Station::query()->firstOrCreate(['name' => $kind->label()], ['kind' => $kind, 'sort_order' => 0, 'is_active' => true]);
    }

    /** Copies the bundled photo to the public disk once and returns its path. */
    private function photo(string $key): ?string
    {
        $source = resource_path("menu-photos/{$key}.jpg");
        if (! is_file($source)) {
            return null;
        }
        $path = self::PHOTO_DIR."/{$key}.jpg";
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $disk->put($path, (string) file_get_contents($source));
        }

        return $path;
    }
}
