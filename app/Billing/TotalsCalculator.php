<?php

namespace App\Billing;

/**
 * Computes an invoice's totals from its lines and a percentage discount.
 *
 * Rules, in order:
 *   1. Each line's net is rounded on its own (quantity × unit price).
 *   2. The discount is a percentage of the subtotal, rounded once.
 *   3. Tax is computed per line on the line's discounted net, so lines with different rates
 *      each carry their own tax, then summed. Rounding per line is what tax offices expect.
 *   4. Total = subtotal − discount + tax.
 *
 * Because step 3 rounds the discount per line and step 2 rounds it once, the two can differ
 * by a cent on awkward numbers. The total always uses step 2's figure; the tax uses step 3's.
 */
final class TotalsCalculator
{
    /** @param  list<LineItem>  $lines */
    public function calculate(array $lines, float $discountPercent = 0): Totals
    {
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new \InvalidArgumentException('Discount must be between 0 and 100 percent.');
        }

        if ($lines === []) {
            return Totals::zero();
        }

        $subtotal = array_sum(array_map(fn (LineItem $line) => $line->net(), $lines));
        $discount = self::percentOf($subtotal, $discountPercent);

        $tax = 0;
        foreach ($lines as $line) {
            $taxable = $line->net() - self::percentOf($line->net(), $discountPercent);
            $tax += self::percentOf($taxable, $line->taxRate);
        }

        return new Totals($subtotal, $discount, $tax, $subtotal - $discount + $tax);
    }

    private static function percentOf(int $amount, float $percent): int
    {
        return (int) round($amount * $percent / 100, 0, PHP_ROUND_HALF_UP);
    }
}
