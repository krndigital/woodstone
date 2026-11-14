<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Validation\ValidationException;

/**
 * The only place bill totals are worked out. Integer paisa; percents as basis points
 * ("10" → 1000, "13.5" → 1350). Half-up rounding. Order: discount → service charge → VAT.
 * VAT applies only to the share of items marked VAT applicable (FR-5.6).
 */
final class BillCalculator
{
    /**
     * @return array{subtotal: int, vatable: int, discount: int, discount_bp: int, service: int, vat: int, total: int, service_percent: string, vat_percent: string}
     */
    public static function forOrder(Order $order, ?string $discountType, string $discountValue): array
    {
        $subtotal = 0;
        $vatable = 0;

        /** @var iterable<OrderItem> $lines */
        $lines = $order->items()->with('menuItem:id,vat_applicable')->where('status', '!=', OrderItemStatus::Void->value)->get();
        foreach ($lines as $line) {
            $amount = Money::toPaisa($line->line_total);
            $subtotal += $amount;
            if ($line->menuItem === null || $line->menuItem->vat_applicable) {
                $vatable += $amount;
            }
        }

        return self::compute($subtotal, $vatable, $discountType, $discountValue, Settings::string('service_charge_percent'), Settings::string('vat_percent'));
    }

    /**
     * @return array{subtotal: int, vatable: int, discount: int, discount_bp: int, service: int, vat: int, total: int, service_percent: string, vat_percent: string}
     */
    public static function compute(int $subtotal, int $vatable, ?string $discountType, string $discountValue, string $servicePercent, string $vatPercent): array
    {
        $value = Money::toPaisa($discountValue === '' ? '0' : $discountValue);

        $discount = match ($discountType) {
            'percent' => $value > 10000
                ? throw ValidationException::withMessages(['discount_value' => __('Discount cannot be more than 100%.')])
                : self::bp($subtotal, $value),
            'amount' => $value > $subtotal
                ? throw ValidationException::withMessages(['discount_value' => __('Discount cannot be more than the bill.')])
                : $value,
            default => 0,
        };

        $taxable = $subtotal - $discount;
        $service = self::bp($taxable, Money::toPaisa($servicePercent));
        $vatBase = $subtotal === 0 ? 0 : intdiv(($taxable + $service) * $vatable * 2 + $subtotal, $subtotal * 2);
        $vat = self::bp($vatBase, Money::toPaisa($vatPercent));

        return [
            'subtotal' => $subtotal,
            'vatable' => $vatable,
            'discount' => $discount,
            'discount_bp' => $subtotal === 0 ? 0 : intdiv($discount * 10000 + intdiv($subtotal, 2), $subtotal),
            'service' => $service,
            'vat' => $vat,
            'total' => $taxable + $service + $vat,
            'service_percent' => $servicePercent,
            'vat_percent' => $vatPercent,
        ];
    }

    /**
     * $paisa × basis points / 10000, rounded half up (inputs are never negative).
     */
    private static function bp(int $paisa, int $bp): int
    {
        return intdiv($paisa * $bp + 5000, 10000);
    }
}
