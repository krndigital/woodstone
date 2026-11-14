<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Settings;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qr = 'qr';
    case Card = 'card';
    case Bank = 'bank';
    case Due = 'due';
    case Points = 'points';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Qr => 'QR',
            self::Card => 'Card',
            self::Bank => 'Bank transfer',
            self::Due => 'On due',
            self::Points => 'Loyalty points',
        };
    }

    public function isEnabled(): bool
    {
        return match ($this) {
            self::Cash => true,
            self::Qr => Settings::bool('payment_qr'),
            self::Card => Settings::bool('payment_card'),
            self::Bank => Settings::bool('payment_bank'),
            self::Due => Settings::bool('payment_due'),
            self::Points => Settings::bool('loyalty_enabled'),
        };
    }

    /**
     * Methods that bring money in now (used when receiving dues).
     *
     * @return list<self>
     */
    public static function receivable(): array
    {
        return [self::Cash, self::Qr, self::Card, self::Bank];
    }
}
