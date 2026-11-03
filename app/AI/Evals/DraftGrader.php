<?php

namespace App\AI\Evals;

use App\AI\InvoiceDraft;

// Grades one InvoiceDraft against the expectation from the eval file. Every check is
// deterministic: exact for numbers and names, a contained keyword for free text. A case
// passes when every check passes; the failures are returned so a report can show why.
final class DraftGrader
{
    /** @return list<string> the checks that failed, empty when the case passed */
    public static function grade(InvoiceDraft $draft, array $expect): array
    {
        $failed = [];
        $check = function (string $name, bool $ok) use (&$failed): void {
            if (! $ok) {
                $failed[] = $name;
            }
        };

        if (array_key_exists('clientName', $expect)) {
            $actual = trim((string) $draft->clientName) === '' ? null : $draft->clientName;
            $check("client {$actual} ≠ ".($expect['clientName'] ?? 'null'), $actual === $expect['clientName']);
        }
        if (isset($expect['currency'])) {
            $check("currency {$draft->currency} ≠ {$expect['currency']}", $draft->currency === $expect['currency']);
        }
        if (isset($expect['discountPercent'])) {
            $check("discount {$draft->discountPercent} ≠ {$expect['discountPercent']}", abs($draft->discountPercent - $expect['discountPercent']) < 0.01);
        }
        if (isset($expect['notes'])) {
            $check("notes lack \"{$expect['notes']}\"", str_contains(strtolower((string) $draft->notes), strtolower($expect['notes'])));
        }
        if (array_key_exists('unresolved', $expect)) {
            $check(
                $expect['unresolved'] === null ? 'unresolved should be empty: '.$draft->unresolved : "unresolved lacks \"{$expect['unresolved']}\"",
                $expect['unresolved'] === null ? blank($draft->unresolved) : str_contains(strtolower((string) $draft->unresolved), strtolower($expect['unresolved'])),
            );
        }
        if (isset($expect['lines'])) {
            $check('line count '.count($draft->lines).' ≠ '.count($expect['lines']), count($draft->lines) === count($expect['lines']));
            foreach ($expect['lines'] as $i => $line) {
                $actual = $draft->lines[$i] ?? null;
                if ($actual === null) {
                    continue;
                }
                $check("line {$i} description lacks \"{$line['description']}\"", str_contains(strtolower($actual->description), strtolower($line['description'])));
                $check("line {$i} quantity {$actual->quantity} ≠ {$line['quantity']}", abs($actual->quantity - $line['quantity']) < 0.01);
                $check("line {$i} price {$actual->unitPrice} ≠ {$line['unitPrice']}", abs($actual->unitPrice - $line['unitPrice']) < 0.01);
                if (array_key_exists('taxRate', $line)) {
                    $check("line {$i} tax ".($actual->taxRate ?? 'null').' ≠ '.($line['taxRate'] ?? 'null'), $actual->taxRate === null ? $line['taxRate'] === null : ($line['taxRate'] !== null && abs($actual->taxRate - $line['taxRate']) < 0.01));
                }
            }
        }

        return $failed;
    }
}
