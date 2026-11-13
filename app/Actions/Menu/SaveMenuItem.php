<?php

declare(strict_types=1);

namespace App\Actions\Menu;

use App\Models\MenuItem;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a menu item with its modifiers. Every price or cost change is audited
 * with the old and new value (requirements §4).
 */
final class SaveMenuItem
{
    /**
     * @param  array<string, mixed>  $data  validated input from MenuItemRequest
     */
    public function handle(array $data, ?MenuItem $item = null): MenuItem
    {
        return DB::transaction(function () use ($data, $item): MenuItem {
            $creating = $item === null;
            $item ??= new MenuItem;
            $before = $creating ? null : ['price' => $item->price, 'cost' => $item->cost, 'name' => $item->name];

            $item->fill([
                'code' => $data['code'],
                'category_id' => $data['category_id'],
                'station_id' => $data['station_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'cost' => $data['cost'] ?? null,
                'is_veg' => (bool) ($data['is_veg'] ?? false),
                'vat_applicable' => (bool) ($data['vat_applicable'] ?? true),
                'show_on_qr' => (bool) ($data['show_on_qr'] ?? true),
                'available_from' => self::time($data['available_from'] ?? null),
                'available_to' => self::time($data['available_to'] ?? null),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
            $item->save();

            /** @var list<array{name: string, price?: string|null}> $modifiers */
            $modifiers = $data['modifiers'] ?? [];
            $item->modifiers()->delete();
            foreach (array_values($modifiers) as $i => $modifier) {
                $item->modifiers()->create([
                    'name' => $modifier['name'],
                    'price' => $modifier['price'] ?? '0',
                    'sort_order' => $i,
                ]);
            }

            if ($creating) {
                Audit::record('menu.item_created', $item, ['code' => $item->code, 'price' => $item->price]);
            } else {
                $after = ['price' => $item->price, 'cost' => $item->cost, 'name' => $item->name];
                Audit::record('menu.item_updated', $item, ['before' => $before, 'after' => $after]);

                if ($before !== null && $before['price'] !== $item->price) {
                    Audit::record('menu.price_changed', $item, ['old' => $before['price'], 'new' => $item->price]);
                }
            }

            return $item->load('modifiers');
        });
    }

    private static function time(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? substr($value, 0, 5).':00' : null;
    }
}
