<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Writes the order item status history (FR-3.5): sent, preparing, ready, served, void.
 */
final class ItemEvents
{
    /**
     * @param  iterable<int>  $itemIds
     */
    public static function log(iterable $itemIds, string $status, ?int $userId): void
    {
        $now = now();
        $rows = [];
        foreach ($itemIds as $id) {
            $rows[] = ['order_item_id' => $id, 'status' => $status, 'user_id' => $userId, 'created_at' => $now];
        }
        if ($rows !== []) {
            DB::table('order_item_events')->insert($rows);
        }
    }
}
