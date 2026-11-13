<?php

declare(strict_types=1);

namespace App\Enums;

enum StationKind: string
{
    case Kitchen = 'kitchen';
    case Bar = 'bar';
    case Counter = 'counter';

    public function label(): string
    {
        return match ($this) {
            self::Kitchen => 'Kitchen',
            self::Bar => 'Bar',
            self::Counter => 'Counter',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $k): array => ['value' => $k->value, 'label' => $k->label()], self::cases());
    }
}
