<?php

use App\Models\ClaudeCall;
use App\Models\Company;
use Tests\Support\FakeClaude;

// The eval command against the fake: it grades, it reports, it keeps the usage rows and it
// leaves no fixture company behind. Only the first two cases of the set run, on a budget.
it('grades the set, keeps the usage rows and cleans up the fixture company', function () {
    FakeClaude::bind(
        FakeClaude::json(['clientName' => 'Acme GmbH', 'currency' => 'EUR', 'discountPercent' => 0, 'lines' => [
            ['description' => 'Consulting', 'quantity' => 12, 'unitPrice' => 150], ['description' => 'Setup fee', 'quantity' => 1, 'unitPrice' => 300],
        ]], inputTokens: 1000, outputTokens: 100),
        FakeClaude::json(['clientName' => 'Blue Harbor Ltd', 'currency' => 'USD', 'discountPercent' => 10, 'lines' => [
            ['description' => 'Training', 'quantity' => 3, 'unitPrice' => 900],
        ], 'notes' => 'Travel is billed separately.'], inputTokens: 1000, outputTokens: 100),
    );

    $this->artisan('claude:eval', ['set' => 'invoice-drafts', '--budget' => 0.01, '--min' => 50])
        ->expectsOutputToContain('pass  hours at a rate plus a flat fee')
        ->expectsOutputToContain('FAIL  days, discount, another currency and a note: currency USD ≠ GBP')
        ->expectsOutputToContain('Budget of $0.01 reached; 10 case(s) skipped.')
        ->expectsTable(['model', 'cases', 'passed', 'pass rate', 'cost'], [['claude-opus-5-5', 2, 1, '50%', '$0.012000']])
        ->assertSuccessful();

    expect(ClaudeCall::where('feature', 'draft')->count())->toBe(2)
        ->and(Company::where('name', 'Northwind')->exists())->toBeFalse();
});

it('fails below the minimum pass rate', function () {
    FakeClaude::bind(FakeClaude::json(['clientName' => null, 'currency' => 'EUR', 'discountPercent' => 0, 'lines' => [['description' => 'x', 'quantity' => 1, 'unitPrice' => 1]]]));

    $this->artisan('claude:eval', ['set' => 'invoice-drafts', '--budget' => 0.000001])->assertFailed();
});
