<?php

declare(strict_types=1);

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\MenuCategoryRequest;
use App\Models\MenuCategory;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MenuCategoryController extends Controller
{
    public function store(MenuCategoryRequest $request): RedirectResponse
    {
        $category = MenuCategory::query()->create([
            'name' => $request->string('name')->toString(),
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
            'sort_order' => $request->integer('sort_order'),
            'is_active' => true,
        ]);

        Audit::record('menu.category_created', $category, ['name' => $category->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category :name added.', ['name' => $category->name])]);

        return back();
    }

    public function update(MenuCategoryRequest $request, MenuCategory $category): RedirectResponse
    {
        $active = $request->boolean('is_active', true);

        if (! $active && $category->items()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['is_active' => __('Move or archive the items in this category first.')]);
        }

        $category->update([
            'name' => $request->string('name')->toString(),
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $active,
        ]);

        Audit::record('menu.category_updated', $category, ['name' => $category->name, 'is_active' => $active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return back();
    }

    /**
     * Only empty categories can be deleted; anything with items or subcategories is kept for history.
     */
    public function destroy(MenuCategory $category): RedirectResponse
    {
        if ($category->items()->exists() || $category->children()->exists()) {
            throw ValidationException::withMessages(['category' => __('This category still has items or subcategories. Turn it off instead.')]);
        }

        Audit::record('menu.category_deleted', null, ['id' => $category->id, 'name' => $category->name]);
        $category->delete();

        return back();
    }
}
