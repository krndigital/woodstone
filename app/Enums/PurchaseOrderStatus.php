<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Partial = 'partial';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Partial => 'Partly received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::Draft, self::Sent, self::Partial], true);
    }
}
