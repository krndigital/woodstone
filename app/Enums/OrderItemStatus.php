<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Draft = on the order but not sent to the kitchen yet ("hold"). Once sent, an item can only be
 * voided with a reason and manager approval (FR-2).
 */
enum OrderItemStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Not sent',
            self::Sent => 'Sent',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Served => 'Served',
            self::Void => 'Void',
        };
    }

    /**
     * Next status when the kitchen taps "bump" (FR-5).
     */
    public function kitchenNext(): ?self
    {
        return match ($this) {
            self::Sent => self::Preparing,
            self::Preparing => self::Ready,
            default => null,
        };
    }

    public function isSent(): bool
    {
        return in_array($this, [self::Sent, self::Preparing, self::Ready, self::Served], true);
    }

    /**
     * @return list<self>
     */
    public static function inKitchen(): array
    {
        return [self::Sent, self::Preparing, self::Ready];
    }
}
