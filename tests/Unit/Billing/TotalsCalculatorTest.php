<?php

use App\Billing\LineItem;
use App\Billing\TotalsCalculator;

// A unit test: one class, plain PHP values in, plain values out. No container, no database.

it('is zero for an invoice with no lines', function () {
    expect((new TotalsCalculator)->calculate([])->toArray())
        ->toBe(['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0]);
});

it('adds tax on a single line', function () {
    $totals = (new TotalsCalculator)->calculate([
        new LineItem(quantity: 2, unitPrice: 50_00, taxRate: 20),
    ]);

    expect($totals->subtotal)->toBe(100_00)
        ->and($totals->tax)->toBe(20_00)
        ->and($totals->total)->toBe(120_00);
});

it('taxes each line at its own rate', function () {
    $totals = (new TotalsCalculator)->calculate([
        new LineItem(quantity: 1, unitPrice: 100_00, taxRate: 20),   // design work
        new LineItem(quantity: 1, unitPrice: 100_00, taxRate: 7),    // books, reduced rate
        new LineItem(quantity: 1, unitPrice: 100_00, taxRate: 0),    // export, zero-rated
    ]);

    expect($totals->tax)->toBe(27_00)
        ->and($totals->total)->toBe(327_00);
});

it('rounds each line on its own, the way the printed invoice does', function () {
    // 3 × 0.33 = 0.99, no surprise. 7 × 1.115 = 7.805 → the line is 7.81, not 7.80.
    $totals = (new TotalsCalculator)->calculate([
        new LineItem(quantity: 3, unitPrice: 33, taxRate: 0),
        new LineItem(quantity: 1.115, unitPrice: 7_00, taxRate: 0),
    ]);

    expect($totals->subtotal)->toBe(99 + 7_81);
});

it('applies a percentage discount before tax', function () {
    $totals = (new TotalsCalculator)->calculate([
        new LineItem(quantity: 10, unitPrice: 80_00, taxRate: 20),   // 800.00
    ], discountPercent: 10);

    expect($totals->toArray())->toBe([
        'subtotal' => 800_00,
        'discount' => 80_00,
        'tax' => 144_00,       // 20 % of 720.00, not of 800.00
        'total' => 864_00,
    ]);
});

it('keeps the total consistent when per-line discounts round differently from the invoice discount', function () {
    // Two lines of 0.05 each, 10 % off. Per line the discount is 0.005 → rounds to 0.01 each.
    // On the subtotal (0.10) it is 0.01. The tax base loses a cent; the total does not.
    $totals = (new TotalsCalculator)->calculate([
        new LineItem(quantity: 1, unitPrice: 5, taxRate: 20),
        new LineItem(quantity: 1, unitPrice: 5, taxRate: 20),
    ], discountPercent: 10);

    expect($totals->subtotal)->toBe(10)
        ->and($totals->discount)->toBe(1)
        ->and($totals->tax)->toBe(2)   // 20 % of (4 + 4) = 1.6 → 2
        ->and($totals->total)->toBe(10 - 1 + 2);
});

it('handles fractional quantities such as hours', function () {
    $totals = (new TotalsCalculator)->calculate([
        new LineItem(quantity: 1.5, unitPrice: 90_00, taxRate: 20),
    ]);

    expect($totals->subtotal)->toBe(135_00)->and($totals->total)->toBe(162_00);
});

it('rejects a discount outside 0 to 100 percent', function (float $discount) {
    (new TotalsCalculator)->calculate([new LineItem(1, 100, 0)], $discount);
})->with([-1, 100.5])->throws(InvalidArgumentException::class);

it('rejects lines that cannot exist', function (float $quantity, int $unitPrice, float $taxRate) {
    new LineItem($quantity, $unitPrice, $taxRate);
})->with([
    'zero quantity' => [0, 100, 20],
    'negative quantity' => [-1, 100, 20],
    'negative price' => [1, -100, 20],
    'tax over 100' => [1, 100, 101],
])->throws(InvalidArgumentException::class);
