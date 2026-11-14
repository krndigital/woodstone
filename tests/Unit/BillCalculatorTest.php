<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BillCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillCalculatorTest extends TestCase
{
    public function test_zero_tax_by_default_shape(): void
    {
        $r = BillCalculator::compute(100000, 100000, null, '0', '0', '0');
        $this->assertSame(['discount' => 0, 'service' => 0, 'vat' => 0, 'total' => 100000], array_intersect_key($r, array_flip(['discount', 'service', 'vat', 'total'])));
    }

    public function test_discount_then_service_then_vat_with_half_up_rounding(): void
    {
        // Rs 1,000; 10% discount → 900; 10% service → 90; 13% VAT on 990 → 128.70; total 1,118.70
        $r = BillCalculator::compute(100000, 100000, 'percent', '10', '10', '13');
        $this->assertSame(10000, $r['discount']);
        $this->assertSame(9000, $r['service']);
        $this->assertSame(12870, $r['vat']);
        $this->assertSame(111870, $r['total']);
        $this->assertSame(1000, $r['discount_bp']);

        // Rs 333.33 with 15% → 49.9995 → 50.00
        $this->assertSame(5000, BillCalculator::compute(33333, 33333, 'percent', '15', '0', '0')['discount']);
    }

    public function test_vat_only_on_vat_applicable_share(): void
    {
        // half the items are VAT exempt
        $r = BillCalculator::compute(100000, 50000, null, '0', '0', '13');
        $this->assertSame(6500, $r['vat']);
    }

    public function test_amount_discount_cannot_exceed_bill(): void
    {
        $this->assertSame(10000, BillCalculator::compute(50000, 50000, 'amount', '100', '0', '0')['discount']);

        $this->expectException(ValidationException::class);
        BillCalculator::compute(50000, 50000, 'amount', '600', '0', '0');
    }
}
