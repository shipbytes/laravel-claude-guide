<?php

use App\Billing\DueDate;
use Carbon\CarbonImmutable;

// Dates are fixed, not "now": a unit test must give the same answer on a Sunday in 2031.

it('adds the payment terms to the issue date', function () {
    $due = DueDate::compute(CarbonImmutable::parse('2026-10-05'), 14); // Monday + 14 = Monday

    expect($due->toDateString())->toBe('2026-10-19');
});

it('moves a weekend due date to the following Monday', function (string $issued, int $terms, string $expected) {
    expect(DueDate::compute(CarbonImmutable::parse($issued), $terms)->toDateString())->toBe($expected);
})->with([
    'lands on Saturday' => ['2026-10-10', 14, '2026-10-26'],   // 24 Oct is a Saturday
    'lands on Sunday' => ['2026-10-11', 14, '2026-10-26'],     // 25 Oct is a Sunday
    'lands on Friday, untouched' => ['2026-10-09', 14, '2026-10-23'],
]);

it('treats zero terms as due on the issue date itself', function () {
    expect(DueDate::compute(CarbonImmutable::parse('2026-10-21'), 0)->toDateString())->toBe('2026-10-21');
});

it('does not mutate the issue date it was given', function () {
    $issued = CarbonImmutable::parse('2026-10-05');
    DueDate::compute($issued, 30);

    expect($issued->toDateString())->toBe('2026-10-05');
});

it('rejects negative terms', function () {
    DueDate::compute(CarbonImmutable::parse('2026-10-05'), -1);
})->throws(InvalidArgumentException::class);
