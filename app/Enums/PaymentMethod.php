<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Settings;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qr = 'qr';
    case Card = 'card';
    case Due = 'due';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Qr => 'QR',
            self::Card => 'Card',
            self::Due => 'On due',
        };
    }

    public function isEnabled(): bool
    {
        return match ($this) {
            self::Cash => true,
            self::Qr => Settings::bool('payment_qr'),
            self::Card => Settings::bool('payment_card'),
            self::Due => Settings::bool('payment_due'),
        };
    }

    /**
     * Methods that bring money in now (used when receiving dues).
     *
     * @return list<self>
     */
    public static function receivable(): array
    {
        return [self::Cash, self::Qr, self::Card];
    }
}
