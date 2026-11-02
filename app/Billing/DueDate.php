<?php

namespace App\Billing;

use Carbon\CarbonImmutable;

/**
 * An invoice is due the given number of days after it was issued. When that lands on a
 * weekend the due date moves to the Monday: nobody pays on a Sunday, and chasing a client
 * on a Saturday for a Saturday due date makes you look like a robot.
 */
final class DueDate
{
    public static function compute(CarbonImmutable $issuedAt, int $termsDays): CarbonImmutable
    {
        if ($termsDays < 0) {
            throw new \InvalidArgumentException('Payment terms cannot be negative.');
        }

        $due = $issuedAt->addDays($termsDays);

        return match (true) {
            $due->isSaturday() => $due->addDays(2),
            $due->isSunday() => $due->addDay(),
            default => $due,
        };
    }
}
