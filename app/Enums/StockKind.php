<?php

declare(strict_types=1);

namespace App\Enums;

enum StockKind: string
{
    case Ingredient = 'ingredient';
    case Resale = 'resale';
    case Asset = 'asset';

    public function label(): string
    {
        return match ($this) {
            self::Ingredient => 'Raw ingredient',
            self::Resale => 'Resale item',
            self::Asset => 'Asset / capex',
        };
    }
}
