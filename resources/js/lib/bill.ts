import { toPaisa } from '@/lib/money';

export type BillTotals = {
    subtotal: number;
    discount: number;
    discountBp: number;
    service: number;
    vat: number;
    roundOff: number;
    total: number;
};

const bp = (paisa: number, basis: number): number =>
    Math.floor((paisa * basis + 5000) / 10000);

/**
 * Mirror of App\Support\BillCalculator for the live preview. The server recomputes on save.
 */
export function computeBill(
    subtotal: number,
    vatable: number,
    discountType: 'percent' | 'amount' | null,
    discountValue: string,
    servicePercent: string,
    vatPercent: string,
    roundOff = false,
): BillTotals {
    const value = toPaisa(discountValue || '0');
    let discount = 0;
    if (discountType === 'percent')
        discount = bp(subtotal, Math.min(value, 10000));
    if (discountType === 'amount') discount = Math.min(value, subtotal);

    const taxable = subtotal - discount;
    const service = bp(taxable, toPaisa(servicePercent));
    const vatBase =
        subtotal === 0
            ? 0
            : Math.floor(
                  ((taxable + service) * vatable * 2 + subtotal) /
                      (subtotal * 2),
              );
    const vat = bp(vatBase, toPaisa(vatPercent));

    return {
        subtotal,
        discount,
        discountBp:
            subtotal === 0
                ? 0
                : Math.floor(
                      (discount * 10000 + Math.floor(subtotal / 2)) / subtotal,
                  ),
        service,
        vat,
        roundOff: roundOff
            ? Math.floor((taxable + service + vat + 50) / 100) * 100 -
              (taxable + service + vat)
            : 0,
        total: roundOff
            ? Math.floor((taxable + service + vat + 50) / 100) * 100
            : taxable + service + vat,
    };
}

export function paisaToInput(p: number): string {
    return (p / 100).toFixed(2);
}

/**
 * Splits $total into shares proportional to $weights; the rounding remainder goes to the last share.
 */
export function splitByWeights(total: number, weights: number[]): number[] {
    const sum = weights.reduce((a, b) => a + b, 0);
    if (sum === 0) return weights.map(() => 0);
    const shares = weights.map((w) => Math.floor((total * w) / sum));
    const lastNonZero = weights.map((w) => w > 0).lastIndexOf(true);
    shares[lastNonZero] += total - shares.reduce((a, b) => a + b, 0);
    return shares;
}
