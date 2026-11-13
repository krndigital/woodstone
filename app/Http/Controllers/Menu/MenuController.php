<?php

declare(strict_types=1);

namespace App\Http\Controllers\Menu;

use App\Enums\Permission;
use App\Enums\StationKind;
use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemModifier;
use App\Models\Station;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu screen (route behind `can:menu.view`). Cost and margin are only sent to users who can
 * edit the menu — other staff see prices, not food costs.
 */
class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $canManage = $user->hasPermission(Permission::ManageMenu);
        $now = now();

        $items = MenuItem::query()
            ->with(['modifiers', 'category:id,name,parent_id', 'station:id,name'])
            ->when(! $canManage, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
                'category_id' => $item->category_id,
                'category' => $item->category?->name,
                'station_id' => $item->station_id,
                'station' => $item->station?->name,
                'price' => $item->price,
                'cost' => $canManage ? $item->cost : null,
                'margin' => $canManage ? $item->marginPercent() : null,
                'is_veg' => $item->is_veg,
                'vat_applicable' => $item->vat_applicable,
                'show_on_qr' => $item->show_on_qr,
                'is_available' => $item->is_available,
                'is_active' => $item->is_active,
                'orderable_now' => $item->isOrderableAt($now),
                'available_from' => $item->available_from === null ? null : substr($item->available_from, 0, 5),
                'available_to' => $item->available_to === null ? null : substr($item->available_to, 0, 5),
                'image_url' => $item->imageUrl(),
                'sort_order' => $item->sort_order,
                'modifiers' => $item->modifiers->map(fn (MenuItemModifier $m): array => ['name' => $m->name, 'price' => $m->price])->values(),
            ])
            ->values();

        $categories = MenuCategory::query()
            ->withCount(['items' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MenuCategory $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'parent_id' => $c->parent_id,
                'sort_order' => $c->sort_order,
                'is_active' => $c->is_active,
                'items_count' => (int) $c->getAttribute('items_count'),
            ])
            ->values();

        $stations = Station::query()
            ->withCount(['items' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Station $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'kind' => $s->kind->value,
                'sort_order' => $s->sort_order,
                'is_active' => $s->is_active,
                'items_count' => (int) $s->getAttribute('items_count'),
            ])
            ->values();

        return Inertia::render('menu/Index', [
            'items' => $items,
            'categories' => $categories,
            'stations' => $stations,
            'stationKinds' => StationKind::options(),
            'can' => [
                'manage' => $canManage,
                'availability' => $user->hasPermission(Permission::SetItemAvailability),
            ],
        ]);
    }
}
