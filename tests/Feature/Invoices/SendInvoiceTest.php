<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create(['invoice_prefix' => 'INV', 'payment_terms_days' => 14]);
    $this->user = User::factory()->for($this->company)->create();
    $this->client = Client::factory()->for($this->company)->create();
});

function draftFor(Company $company, Client $client): Invoice
{
    return Invoice::factory()->for($company)->for($client)
        ->has(InvoiceLine::factory()->state(['quantity' => 1, 'unit_price' => 100_00, 'tax_rate' => 20]), 'lines')
        ->create();
}

it('numbers, dates and sends a draft', function () {
    $this->freezeTime(); // the test runs at the same instant everywhere
    $this->travelTo('2026-10-05'); // a Monday: due 14 days later is also a Monday

    $invoice = draftFor($this->company, $this->client);

    $this->actingAs($this->user)
        ->post("/invoices/{$invoice->id}/send")
        ->assertRedirect("/invoices/{$invoice->id}")
        ->assertSessionHas('status', 'Invoice INV-2026-0001 sent.');

    $invoice->refresh();

    expect($invoice)
        ->number->toBe('INV-2026-0001')
        ->status->value->toBe('sent')
        ->issued_at->toDateString()->toBe('2026-10-05')
        ->due_at->toDateString()->toBe('2026-10-19')
        ->total->toBe(120_00);
});

it('continues the company\'s sequence and leaves other companies alone', function () {
    $this->travelTo('2026-03-01');

    Invoice::factory()->for($this->company)->for($this->client)->sent()->create(['number' => 'INV-2026-0041']);
    $next = draftFor($this->company, $this->client);
    $elsewhere = draftFor($other = Company::factory()->create(), Client::factory()->for($other)->create());

    $this->actingAs($this->user)->post("/invoices/{$next->id}/send");
    $this->actingAs(User::factory()->for($other)->create())->post("/invoices/{$elsewhere->id}/send");

    expect($next->refresh()->number)->toBe('INV-2026-0042')
        ->and($elsewhere->refresh()->number)->toBe('INV-2026-0001');
});

it('keeps counting past 9999, where a plain string sort would go wrong', function () {
    $this->travelTo('2026-06-01');

    Invoice::factory()->for($this->company)->for($this->client)->sent()->create(['number' => 'INV-2026-9999']);
    Invoice::factory()->for($this->company)->for($this->client)->sent()->create(['number' => 'INV-2026-10000']);
    $next = draftFor($this->company, $this->client);

    $this->actingAs($this->user)->post("/invoices/{$next->id}/send");

    expect($next->refresh()->number)->toBe('INV-2026-10001');
});

it('refuses to send an invoice with no lines', function () {
    $empty = Invoice::factory()->for($this->company)->for($this->client)->create();

    $this->actingAs($this->user)
        ->from("/invoices/{$empty->id}")
        ->post("/invoices/{$empty->id}/send")
        ->assertRedirect("/invoices/{$empty->id}")
        ->assertSessionHasErrors('invoice');

    expect($empty->refresh())->status->value->toBe('draft')->number->toBeNull();
});

it('cannot be sent twice', function () {
    $invoice = Invoice::factory()->for($this->company)->for($this->client)->sent()->create();

    $this->actingAs($this->user)->post("/invoices/{$invoice->id}/send")->assertForbidden();
});

it('cannot be sent by another company', function () {
    $invoice = draftFor($this->company, $this->client);

    $this->actingAs(User::factory()->create())->post("/invoices/{$invoice->id}/send")->assertForbidden();

    expect($invoice->refresh()->number)->toBeNull();
});
