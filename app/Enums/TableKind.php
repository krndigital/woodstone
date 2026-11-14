<?php

declare(strict_types=1);

namespace App\Enums;

enum TableKind: string
{
    case Table = 'table';
    case Cabin = 'cabin';

    public function label(): string
    {
        return $this === self::Cabin ? 'Cabin' : 'Table';
    }
}
