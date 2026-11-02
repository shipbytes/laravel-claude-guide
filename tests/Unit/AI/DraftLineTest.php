<?php

use App\AI\DraftLine;

it('converts a major-unit price to the currency\'s minor units', function (string $currency, float $price, int $minor) {
    $line = DraftLine::fromArray(['description' => 'x', 'quantity' => 1, 'unitPrice' => $price]);

    expect($line->unitPriceMinor($currency))->toBe($minor);
})->with([
    'euros' => ['EUR', 150.00, 15000],
    'cents round, not truncate' => ['USD', 19.999, 2000],
    'yen have no minor unit' => ['JPY', 12000.0, 12000],
    'dinar have three' => ['KWD', 1.5, 1500],
]);
