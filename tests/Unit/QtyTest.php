<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Qty;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QtyTest extends TestCase
{
    public function test_parse_format_and_rounding(): void
    {
        $this->assertSame(1500, Qty::toMilli('1.5'));
        $this->assertSame(-250, Qty::toMilli('-0.25'));
        $this->assertSame('1.500', Qty::fromMilli(1500));
        $this->assertSame('-0.250', Qty::fromMilli(-250));
        $this->assertSame(123456, Qty::toCost('12.3456'));
        $this->assertSame('0.0500', Qty::fromCost(500));
        $this->assertSame(2, Qty::div(5, 3));
        $this->assertSame(-2, Qty::div(-5, 3));
        $this->assertSame(1, Qty::div(3, 2));
        $this->assertSame(0, Qty::div(1, 0));

        $this->expectException(InvalidArgumentException::class);
        Qty::toMilli('1.2345');
    }
}
