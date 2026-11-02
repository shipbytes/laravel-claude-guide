<?php

namespace App\Billing;

/**
 * One line of an invoice as the calculator sees it: no Eloquent, no database, no ids.
 * Quantity and tax rate are plain numbers; the unit price is in minor units (cents).
 */
final readonly class LineItem
{
    public function __construct(
        public float $quantity,
        public int $unitPrice,
        public float $taxRate,
    ) {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }
        if ($unitPrice < 0) {
            throw new \InvalidArgumentException('Unit price cannot be negative.');
        }
        if ($taxRate < 0 || $taxRate > 100) {
            throw new \InvalidArgumentException('Tax rate must be between 0 and 100.');
        }
    }

    /** The line's net amount in minor units, rounded half up like an accountant would. */
    public function net(): int
    {
        return (int) round($this->unitPrice * $this->quantity, 0, PHP_ROUND_HALF_UP);
    }
}
