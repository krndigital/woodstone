<?php

declare(strict_types=1);

namespace App\Enums;

enum TableStatus: string
{
    case Free = 'free';
    case Occupied = 'occupied';
    case BillAsked = 'bill';
    case Reserved = 'reserved';
    case Cleaning = 'cleaning';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Occupied => 'Occupied',
            self::BillAsked => 'Bill asked',
            self::Reserved => 'Reserved',
            self::Cleaning => 'Needs cleaning',
        };
    }

    /**
     * Statuses staff may set by hand. Occupied and Bill asked are set by orders and bills.
     *
     * @return list<self>
     */
    public function manualTargets(): array
    {
        return match ($this) {
            self::Free => [self::Reserved],
            self::Reserved => [self::Free],
            self::Cleaning => [self::Free],
            self::Occupied, self::BillAsked => [],
        };
    }

    public function acceptsNewOrder(): bool
    {
        return $this === self::Free || $this === self::Reserved;
    }
}
