<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BsDate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BsDateTest extends TestCase
{
    public function test_known_dates_convert_both_ways(): void
    {
        $this->assertSame([2083, 4, 1], BsDate::fromAd(CarbonImmutable::parse('2026-07-17')));
        $this->assertSame('2025-04-14', BsDate::toAd(2082, 1, 1)->toDateString());
        $this->assertSame('2024-04-13', BsDate::toAd(2081, 1, 1)->toDateString());
        $this->assertSame('1 Shrawan 2083', BsDate::long(CarbonImmutable::parse('2026-07-17')));

        for ($d = CarbonImmutable::parse('2020-01-01'); $d->lessThan(CarbonImmutable::parse('2031-01-01')); $d = $d->addDays(37)) {
            [$y, $m, $day] = BsDate::fromAd($d);
            $this->assertSame($d->toDateString(), BsDate::toAd($y, $m, $day)->toDateString());
        }
    }

    public function test_fiscal_year_runs_shrawan_to_asar(): void
    {
        $this->assertSame(['label' => '2083/84', 'starts_on' => '2026-07-17'], BsDate::fiscalYear(CarbonImmutable::parse('2026-11-14')));
        $this->assertSame('2082/83', BsDate::fiscalYear(CarbonImmutable::parse('2026-07-16'))['label']);

        $this->expectException(InvalidArgumentException::class);
        BsDate::toAd(2083, 13, 1);
    }
}
