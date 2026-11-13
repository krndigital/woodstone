<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MenuItem;
use App\Support\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    public function test_money_is_converted_without_float_errors(): void
    {
        $this->assertSame(22050, Money::toPaisa('220.5'));
        $this->assertSame(22000, Money::toPaisa('220'));
        $this->assertSame(1, Money::toPaisa('0.01'));
        $this->assertSame('220.50', Money::fromPaisa(22050));
        $this->assertSame('-0.05', Money::fromPaisa(-5));
        $this->assertSame(75, Money::marginPercent('200.00', '50.00'));
        $this->assertNull(Money::marginPercent('200.00', null));

        $this->expectException(InvalidArgumentException::class);
        Money::toPaisa('1.234');
    }

    public function test_time_windows_including_past_midnight(): void
    {
        $item = new MenuItem;
        $item->forceFill(['is_active' => true, 'is_available' => true, 'available_from' => '18:00:00', 'available_to' => '02:00:00']);

        $this->assertTrue($item->isOrderableAt(CarbonImmutable::parse('2026-11-13 20:00')));
        $this->assertTrue($item->isOrderableAt(CarbonImmutable::parse('2026-11-13 01:30')));
        $this->assertFalse($item->isOrderableAt(CarbonImmutable::parse('2026-11-13 12:00')));

        $item->forceFill(['available_from' => null, 'available_to' => null, 'is_available' => false]);
        $this->assertFalse($item->isOrderableAt(CarbonImmutable::parse('2026-11-13 12:00')));
    }
}
