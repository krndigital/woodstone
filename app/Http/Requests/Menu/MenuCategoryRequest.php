<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use App\Enums\Permission;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::ManageMenu);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /**
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            // Only one level: a parent must itself be a top-level category.
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_categories', 'id')->whereNull('parent_id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $current = $this->route('category');
                $currentId = $current instanceof MenuCategory ? $current->id : null;
                $parentId = $this->input('parent_id');

                $duplicate = MenuCategory::query()
                    ->whereRaw('lower(name) = ?', [mb_strtolower((string) $this->input('name'))])
                    ->when($parentId === null || $parentId === '', fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', (int) $parentId))
                    ->when($currentId !== null, fn ($q) => $q->whereKeyNot($currentId))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('name', __('A category with this name already exists here.'));
                }

                if ($currentId !== null && (int) $parentId === $currentId) {
                    $validator->errors()->add('parent_id', __('A category cannot be inside itself.'));
                }

                if ($current instanceof MenuCategory && $parentId !== null && $parentId !== '' && $current->children()->exists()) {
                    $validator->errors()->add('parent_id', __('This category has subcategories, so it must stay at the top level.'));
                }
            },
        ];
    }
}
