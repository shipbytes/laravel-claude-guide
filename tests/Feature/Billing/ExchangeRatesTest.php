<?php

use App\Billing\ExchangeRates;
use App\Billing\RatesUnavailable;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Any request that is not faked fails the test instead of leaving the laptop.
    Http::preventStrayRequests();
});

it('fetches a rate and converts in minor units', function () {
    // A closure fake answers from the request, so each base currency gets its own rate.
    Http::fake(['rates.test/*' => fn (Request $r) => Http::response(['base' => $r['base'], 'rates' => [
        'EUR' => ['USD' => 0.92, 'JPY' => 0.0062][$r['base']],
    ]])]);

    $rates = new ExchangeRates;

    expect($rates->rate('USD', 'EUR'))->toBe(0.92)
        ->and($rates->convert(100_00, 'USD', 'EUR'))->toBe(92_00)
        ->and($rates->convert(1000, 'JPY', 'EUR'))->toBe(620);  // ¥1,000 at 0.0062 → €6.20: yen have no minor unit

    Http::assertSent(fn (Request $r) => $r['base'] === 'USD' && $r['symbols'] === 'EUR');
});

it('caches a pair for a day so the API is hit once', function () {
    Http::fake(['rates.test/*' => Http::response(['rates' => ['EUR' => 0.92]])]);

    $rates = new ExchangeRates;
    $rates->rate('USD', 'EUR');
    $rates->rate('USD', 'EUR');
    $rates->convert(5_00, 'USD', 'EUR');

    Http::assertSentCount(1);
});

it('is the identity for the same currency, with no request', function () {
    Http::fake();

    expect((new ExchangeRates)->convert(123, 'EUR', 'EUR'))->toBe(123);
    Http::assertNothingSent();
});

it('turns an API failure into a domain exception', function () {
    Http::fake(['rates.test/*' => Http::response('upstream down', 503)]);

    (new ExchangeRates)->rate('USD', 'EUR');
})->throws(RatesUnavailable::class, 'Could not fetch the USD/EUR rate.');

it('rejects a response without the rate', function () {
    Http::fake(['rates.test/*' => Http::response(['rates' => []])]);

    (new ExchangeRates)->rate('USD', 'EUR');
})->throws(RatesUnavailable::class);

it('shows the invoice total in the company currency on the page', function () {
    Http::fake(['rates.test/*' => Http::response(['rates' => ['EUR' => 0.5]])]);
    $company = Company::factory()->create(['currency' => 'EUR']);
    $invoice = Invoice::factory()->for($company)->for(Client::factory()->for($company))->sent()
        ->create(['currency' => 'USD', 'total' => 200_00]);

    $this->actingAs(User::factory()->for($company)->create())
        ->get("/invoices/{$invoice->id}")
        ->assertSee('≈ €100.00');
});

it('degrades to a note when rates are unavailable', function () {
    Http::fake(['rates.test/*' => Http::response(status: 500)]);
    $company = Company::factory()->create(['currency' => 'EUR']);
    $invoice = Invoice::factory()->for($company)->for(Client::factory()->for($company))->sent()->create(['currency' => 'USD']);

    $this->actingAs(User::factory()->for($company)->create())
        ->get("/invoices/{$invoice->id}")
        ->assertOk()
        ->assertSee('rate unavailable');
});
