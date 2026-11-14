<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

final class EnsureOrderOpen
{
    public static function check(Order $order): void
    {
        if (! $order->isOpen()) {
            throw ValidationException::withMessages(['order' => __('Order #:number is :status and cannot be changed.', ['number' => $order->number, 'status' => mb_strtolower($order->status->label())])]);
        }
    }
}
