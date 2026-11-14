<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Enums\Permission;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff side of guest calls (FR-16.5 to FR-16.10): the ringing banner polls `pending`;
 * Accept stops the ring everywhere; Done closes it. Kitchen can ping waiters ("food ready").
 */
class ServiceCallController extends Controller
{
    public function pending(): JsonResponse
    {
        $calls = ServiceCall::query()->with(['diningTable', 'acceptor:id,name'])
            ->whereIn('status', ['sent', 'accepted'])
            ->where('created_at', '>=', now()->subHours(3))
            ->orderBy('id')->limit(30)->get()
            ->map(fn (ServiceCall $c): array => [
                'id' => $c->id, 'table' => $c->diningTable->label(), 'type' => $c->type, 'type_label' => $c->typeLabel(),
                'note' => $c->note, 'status' => $c->status, 'accepted_by' => $c->acceptor?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values()->all();

        return response()->json(['calls' => $calls, 'now' => now()->toIso8601String()]);
    }

    public function accept(Request $request, ServiceCall $call): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($call->status !== 'sent') {
            return response()->json(['message' => __('Already taken by :name.', ['name' => $call->acceptor->name ?? 'someone'])], 409);
        }
        $call->forceFill(['status' => 'accepted', 'accepted_by' => $user->id, 'accepted_at' => now()])->save();

        return response()->json(['ok' => true]);
    }

    public function done(Request $request, ServiceCall $call): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $call->forceFill([
            'status' => 'done',
            'accepted_by' => $call->accepted_by ?? $user->id,
            'accepted_at' => $call->accepted_at ?? now(),
            'done_at' => now(),
        ])->save();

        if ($call->type === 'bill') {
            $table = $call->diningTable;
            if ($table->status === TableStatus::Occupied) {
                $table->setStatus(TableStatus::BillAsked);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POS / kitchen ping to waiters, e.g. "Food ready for C2" (FR-16.10).
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::UsePos) || $user->hasPermission(Permission::ViewKitchen), 403);
        $data = $request->validate([
            'dining_table_id' => ['required', 'integer', Rule::exists('dining_tables', 'id')],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        $call = new ServiceCall;
        $call->forceFill([
            'dining_table_id' => (int) $data['dining_table_id'], 'type' => 'ping',
            'note' => isset($data['note']) ? (string) $data['note'] : 'Food ready', 'status' => 'sent',
            'ip' => $request->ip(), 'public_id' => (string) Str::ulid(),
        ])->save();
        Audit::record('service.ping', DiningTable::query()->find($call->dining_table_id), ['note' => $call->note], $user);

        return response()->json(['ok' => true]);
    }
}
