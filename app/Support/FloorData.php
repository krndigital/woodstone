<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DiningTable;
use App\Models\Zone;

/**
 * Shared shapes for the floor plan, used by the floor page and the POS table picker.
 */
final class FloorData
{
    /**
     * @return array<int, array{id: int, name: string, sort_order: int, is_active: bool}>
     */
    public static function zones(bool $includeInactive = false): array
    {
        return Zone::query()
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (Zone $z): array => ['id' => $z->id, 'name' => $z->name, 'sort_order' => $z->sort_order, 'is_active' => $z->is_active])
            ->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function tables(bool $includeInactive = false): array
    {
        return DiningTable::query()
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('code')
            ->get()
            ->map(fn (DiningTable $t): array => [
                'id' => $t->id,
                'zone_id' => $t->zone_id,
                'code' => $t->code,
                'label' => $t->label(),
                'kind' => $t->kind->value,
                'seats' => $t->seats,
                'min_spend' => $t->min_spend,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'reserved_for' => $t->reserved_for,
                'reserved_at' => $t->reserved_at?->toIso8601String(),
                'status_changed_at' => $t->status_changed_at?->toIso8601String(),
                'sort_order' => $t->sort_order,
                'is_active' => $t->is_active,
            ])
            ->values()->all();
    }
}
