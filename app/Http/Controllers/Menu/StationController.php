<?php

declare(strict_types=1);

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\StationRequest;
use App\Models\Station;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StationController extends Controller
{
    public function store(StationRequest $request): RedirectResponse
    {
        $station = Station::query()->create([
            'name' => $request->string('name')->toString(),
            'kind' => $request->string('kind')->toString(),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => true,
        ]);

        Audit::record('menu.station_created', $station, ['name' => $station->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Station :name added.', ['name' => $station->name])]);

        return back();
    }

    public function update(StationRequest $request, Station $station): RedirectResponse
    {
        $active = $request->boolean('is_active', true);

        // Turning off a station that still has items would leave their KOTs nowhere to go.
        if (! $active && $station->items()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['is_active' => __('Move this station\'s items to another station first.')]);
        }

        $station->update([
            'name' => $request->string('name')->toString(),
            'kind' => $request->string('kind')->toString(),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $active,
        ]);

        Audit::record('menu.station_updated', $station, ['name' => $station->name, 'is_active' => $active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Station saved.')]);

        return back();
    }
}
