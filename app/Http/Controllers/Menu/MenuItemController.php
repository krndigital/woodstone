<?php

declare(strict_types=1);

namespace App\Http\Controllers\Menu;

use App\Actions\Menu\SaveMenuItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\MenuImageRequest;
use App\Http\Requests\Menu\MenuItemRequest;
use App\Models\MenuItem;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MenuItemController extends Controller
{
    public function store(MenuItemRequest $request, SaveMenuItem $save): RedirectResponse
    {
        $item = $save->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added to the menu.', ['name' => $item->name])]);

        return back();
    }

    public function update(MenuItemRequest $request, MenuItem $item, SaveMenuItem $save): RedirectResponse
    {
        $save->handle($request->validated(), $item);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved :name.', ['name' => $item->name])]);

        return back();
    }

    /**
     * Sold out / back on sale (FR-3.4). Route behind `can:menu.availability`.
     */
    public function toggleAvailability(MenuItem $item): RedirectResponse
    {
        $item->forceFill(['is_available' => ! $item->is_available])->save();

        Audit::record($item->is_available ? 'menu.item_available' : 'menu.item_sold_out', $item, ['code' => $item->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $item->is_available
            ? __(':name is back on sale.', ['name' => $item->name])
            : __(':name is marked sold out.', ['name' => $item->name])]);

        return back();
    }

    /**
     * Items are archived, never deleted, so old orders and reports keep their names.
     */
    public function archive(MenuItem $item): RedirectResponse
    {
        $item->forceFill(['is_active' => false])->save();

        Audit::record('menu.item_archived', $item, ['code' => $item->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name archived.', ['name' => $item->name])]);

        return back();
    }

    public function restore(MenuItem $item): RedirectResponse
    {
        $item->forceFill(['is_active' => true])->save();

        Audit::record('menu.item_restored', $item, ['code' => $item->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name restored.', ['name' => $item->name])]);

        return back();
    }

    public function uploadImage(MenuImageRequest $request, MenuItem $item): RedirectResponse
    {
        $file = $request->file('image');
        abort_if($file === null || is_array($file), 422);

        // Random file name from Laravel; the original client file name is never used.
        $path = $file->store('menu', 'public');
        abort_if($path === false, 500);

        $old = $item->image_path;
        $item->forceFill(['image_path' => $path])->save();

        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }

        Audit::record('menu.image_changed', $item);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo updated.')]);

        return back();
    }

    public function removeImage(MenuItem $item): RedirectResponse
    {
        if ($item->image_path !== null) {
            Storage::disk('public')->delete($item->image_path);
            $item->forceFill(['image_path' => null])->save();
            Audit::record('menu.image_removed', $item);
        }

        return back();
    }
}
