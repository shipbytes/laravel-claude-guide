<?php

use App\Billing\InvoiceNumber;

it('formats prefix, year and a zero-padded sequence', function () {
    expect(InvoiceNumber::format('INV', 2026, 7))->toBe('INV-2026-0007')
        ->and(InvoiceNumber::format('inv', 2026, 12345))->toBe('INV-2026-12345');
});

it('parses what it formats', function (string $prefix, int $year, int $sequence) {
    $number = InvoiceNumber::format($prefix, $year, $sequence);

    expect(InvoiceNumber::parse($number))->toBe(['prefix' => $prefix, 'year' => $year, 'sequence' => $sequence]);
})->with([
    ['INV', 2026, 1],
    ['ACME', 2027, 9999],
    ['R', 2030, 10000],
]);

it('starts at one when there is no previous invoice', function () {
    expect(InvoiceNumber::next(null, 'INV', 2026))->toBe('INV-2026-0001');
});

it('continues the sequence within a year', function () {
    expect(InvoiceNumber::next('INV-2026-0041', 'INV', 2026))->toBe('INV-2026-0042');
});

it('restarts the sequence in a new year', function () {
    expect(InvoiceNumber::next('INV-2026-0041', 'INV', 2027))->toBe('INV-2027-0001');
});

it('refuses strings that are not invoice numbers', function (string $bad) {
    InvoiceNumber::parse($bad);
})->with(['INV-26-0001', 'INV-2026-1', '2026-0001', ''])->throws(InvalidArgumentException::class);
