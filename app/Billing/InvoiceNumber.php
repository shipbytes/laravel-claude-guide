<?php

namespace App\Billing;

/**
 * Invoice numbers look like INV-2026-0007: a prefix, the year, a sequence that restarts each
 * year and is never reused. Deciding the next number needs the last one; that lookup is the
 * caller's job (see the Invoice model), so this class stays free of the database.
 */
final class InvoiceNumber
{
    public static function format(string $prefix, int $year, int $sequence): string
    {
        if ($sequence < 1) {
            throw new \InvalidArgumentException('Sequence starts at 1.');
        }

        return sprintf('%s-%d-%04d', strtoupper($prefix), $year, $sequence);
    }

    /** @return array{prefix: string, year: int, sequence: int} */
    public static function parse(string $number): array
    {
        if (! preg_match('/^([A-Z]+)-(\d{4})-(\d{4,})$/', $number, $m)) {
            throw new \InvalidArgumentException("Not an invoice number: {$number}");
        }

        return ['prefix' => $m[1], 'year' => (int) $m[2], 'sequence' => (int) $m[3]];
    }

    /** The number that follows $last in $year. A new year, or no invoices yet, starts at 1. */
    public static function next(?string $last, string $prefix, int $year): string
    {
        if ($last === null) {
            return self::format($prefix, $year, 1);
        }

        $parsed = self::parse($last);

        return self::format($prefix, $year, $parsed['year'] === $year ? $parsed['sequence'] + 1 : 1);
    }
}
