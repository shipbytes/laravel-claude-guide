<?php

namespace App\Billing;

/** The four numbers printed at the bottom of an invoice, in minor units. */
final readonly class Totals
{
    public function __construct(
        public int $subtotal,
        public int $discount,
        public int $tax,
        public int $total,
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0, 0);
    }

    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
        ];
    }
}
