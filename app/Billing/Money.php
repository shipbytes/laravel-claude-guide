<?php

namespace App\Billing;

use NumberFormatter;

/**
 * Formatting for amounts stored in minor units. Most currencies have 100 minor units to the
 * major one; a few, like the yen, have none, which is exactly the kind of thing that is wrong
 * in production for a year before anyone in Tokyo complains.
 */
final class Money
{
    private const array MINOR_DIGITS = ['JPY' => 0, 'KRW' => 0, 'KWD' => 3, 'BHD' => 3];

    public static function decimals(string $currency): int
    {
        return self::MINOR_DIGITS[strtoupper($currency)] ?? 2;
    }

    public static function toMajor(int $minor, string $currency): float
    {
        return $minor / (10 ** self::decimals($currency));
    }

    public static function format(int $minor, string $currency, string $locale = 'en'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency(self::toMajor($minor, $currency), strtoupper($currency));
    }
}
