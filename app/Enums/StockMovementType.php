<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Wastage = 'wastage';
    case StaffMeal = 'staff_meal';
    case Adjustment = 'adjustment';
    case Count = 'count';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::Purchase => 'Purchase',
            self::Sale => 'Sold',
            self::Wastage => 'Wastage',
            self::StaffMeal => 'Staff meal',
            self::Adjustment => 'Adjustment',
            self::Count => 'Stock count',
            self::Return => 'Return',
        };
    }

    /**
     * Types staff can enter by hand on the stock page (FR-7.5, FR-7.7, FR-7.9).
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Opening, self::Wastage, self::StaffMeal, self::Adjustment, self::Count];
    }
}
