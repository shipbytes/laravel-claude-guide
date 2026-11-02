<?php

use App\Billing\Money;

it('formats minor units in the currency\'s own decimals', function (int $minor, string $currency, string $expected) {
    expect(Money::format($minor, $currency))->toBe($expected);
})->with([
    'euro' => [1234_56, 'EUR', '€1,234.56'],
    'dollar' => [99, 'USD', '$0.99'],
    'yen has no minor unit' => [1234, 'JPY', '¥1,234'],
    // intl separates a code from the number with a non-breaking space, not a space.
    'dinar has three' => [1234, 'KWD', "KWD\u{a0}1.234"],
]);

it('converts to major units', function () {
    expect(Money::toMajor(1234_56, 'EUR'))->toBe(1234.56)
        ->and(Money::toMajor(1234, 'JPY'))->toBe(1234.0);
});

it('respects the locale', function () {
    expect(Money::format(1234_56, 'EUR', 'de_DE'))->toBe("1.234,56\u{a0}€");
});
