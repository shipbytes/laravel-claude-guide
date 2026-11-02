<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

// Factories are the part of a feature test that decides whether it is readable. These show
// the five tools that keep the setup to a few lines.

it('shares one company across a graph with recycle()', function () {
    $company = Company::factory()->create();

    $invoice = Invoice::factory()->recycle($company)
        ->has(InvoiceLine::factory()->count(2), 'lines')
        ->create();

    // Without recycle() the client factory would have created a second company.
    // is() compares keys; toBe() would compare object identity, and these are two instances.
    expect($invoice->company->is($company))->toBeTrue()
        ->and($invoice->client->company_id)->toBe($company->id)
        ->and(Company::count())->toBe(1);
});

it('walks a sequence over the records it creates', function () {
    // States apply in the order they were added. sent() sets a number of its own, so the
    // sequence has to come after it, or the state wins.
    $company = Company::factory()->create();

    $invoices = Invoice::factory()->count(3)->recycle($company)->sent()
        ->sequence(['number' => 'INV-2026-0001'], ['number' => 'INV-2026-0002'], ['number' => 'INV-2026-0003'])
        ->create();

    expect($invoices->pluck('number')->all())->toBe(['INV-2026-0001', 'INV-2026-0002', 'INV-2026-0003']);
});

it('builds a draft with lines and totals in one call', function () {
    $invoice = Invoice::factory()->withLines(3)->create();

    expect($invoice->lines)->toHaveCount(3)
        ->and($invoice->total)->toBeGreaterThan(0)
        ->and($invoice->total)->toBe($invoice->subtotal + $invoice->tax);
});

it('makes a model without saving it, but still saves the parents its definition names', function () {
    $user = User::factory()->make();

    expect($user->exists)->toBeFalse();
    $this->assertDatabaseCount('users', 0);
    // Surprise: Company::factory() in the definition is resolved with create(), even on make().
    $this->assertDatabaseCount('companies', 1);
});

it('keeps unique values unique across a run', function () {
    $clients = Client::factory()->count(20)->recycle(Company::factory()->create())->create();

    expect($clients->pluck('email')->unique())->toHaveCount(20);
});
