<?php

declare(strict_types=1);

namespace App\Http\Controllers\Floor;

use App\Enums\Permission;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Floor\DiningTableRequest;
use App\Http\Requests\Floor\TableStatusRequest;
use App\Http\Requests\Floor\ZoneRequest;
use App\Models\DiningTable;
use App\Models\User;
use App\Models\Zone;
use App\Support\Audit;
use App\Support\FloorData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Floor plan (step 3). Viewing and simple status changes: `floor.view`. Setup: `floor.manage`.
 */
class FloorController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('floor/Index', [
            'zones' => FloorData::zones(includeInactive: $user->hasPermission(Permission::ManageFloor)),
            'tables' => FloorData::tables(includeInactive: $user->hasPermission(Permission::ManageFloor)),
            'can' => [
                'manage' => $user->hasPermission(Permission::ManageFloor),
                'pos' => $user->hasPermission(Permission::UsePos),
            ],
        ]);
    }

    public function updateStatus(TableStatusRequest $request, DiningTable $table): RedirectResponse
    {
        $target = TableStatus::from($request->string('status')->toString());
        $from = $table->status;

        if ($target === TableStatus::Reserved) {
            $table->forceFill([
                'reserved_for' => $request->string('reserved_for')->toString(),
                'reserved_at' => Carbon::parse($request->string('reserved_at')->toString()),
            ]);
        }
        $table->setStatus($target);

        Audit::record('floor.status_changed', $table, ['from' => $from->value, 'to' => $target->value, 'reserved_for' => $table->reserved_for]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':table is now :status.', ['table' => $table->label(), 'status' => $target->label()])]);

        return back();
    }

    public function storeZone(ZoneRequest $request): RedirectResponse
    {
        $zone = Zone::query()->create([
            'name' => $request->string('name')->trim()->toString(),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => true,
        ]);
        Audit::record('floor.zone_created', $zone, ['name' => $zone->name]);

        return back();
    }

    public function updateZone(ZoneRequest $request, Zone $zone): RedirectResponse
    {
        $active = $request->boolean('is_active', true);
        if (! $active && $zone->tables()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['is_active' => __('Turn off or move the tables in this zone first.')]);
        }

        $zone->update(['name' => $request->string('name')->trim()->toString(), 'sort_order' => $request->integer('sort_order'), 'is_active' => $active]);
        Audit::record('floor.zone_updated', $zone, ['name' => $zone->name, 'is_active' => $active]);

        return back();
    }

    public function storeTable(DiningTableRequest $request): RedirectResponse
    {
        $table = new DiningTable($request->safe()->only(['zone_id', 'code', 'kind', 'seats', 'sort_order']));
        $table->forceFill(['status' => TableStatus::Free, 'is_active' => true, 'sort_order' => $request->integer('sort_order')])->save();
        Audit::record('floor.table_created', $table, ['code' => $table->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':table added.', ['table' => $table->label()])]);

        return back();
    }

    public function updateTable(DiningTableRequest $request, DiningTable $table): RedirectResponse
    {
        $active = $request->boolean('is_active', true);
        if (! $active && ! $table->status->acceptsNewOrder()) {
            throw ValidationException::withMessages(['is_active' => __('This table has guests. Close the order first.')]);
        }

        $table->fill($request->safe()->only(['zone_id', 'code', 'kind', 'seats']));
        $table->forceFill(['sort_order' => $request->integer('sort_order'), 'is_active' => $active])->save();
        Audit::record('floor.table_updated', $table, ['code' => $table->code, 'is_active' => $active]);

        return back();
    }
}
