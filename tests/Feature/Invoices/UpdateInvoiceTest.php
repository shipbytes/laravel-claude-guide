<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->client = Client::factory()->for($this->company)->create();
});

it('replaces the lines of a draft and recomputes the totals', function () {
    $invoice = Invoice::factory()->for($this->company)->for($this->client)
        ->has(InvoiceLine::factory()->count(3), 'lines')
        ->create();

    $this->actingAs($this->user)
        ->put("/invoices/{$invoice->id}", [
            'client_id' => $this->client->id,
            'currency' => 'USD',
            'discount_percent' => 0,
            'lines' => [['description' => 'One line now', 'quantity' => 1, 'unit_price' => 100_00, 'tax_rate' => 0]],
        ])
        ->assertRedirect("/invoices/{$invoice->id}");

    $invoice->refresh();

    expect($invoice->lines)->toHaveCount(1)
        ->and($invoice->currency)->toBe('USD')
        ->and($invoice->total)->toBe(100_00);
});

it('refuses to change an invoice that has been sent', function () {
    $invoice = Invoice::factory()->for($this->company)->for($this->client)->sent()->create();

    $this->actingAs($this->user)->get("/invoices/{$invoice->id}/edit")->assertForbidden();

    $this->actingAs($this->user)
        ->put("/invoices/{$invoice->id}", ['client_id' => $this->client->id, 'currency' => 'EUR', 'lines' => []])
        ->assertForbidden();
});

it('refuses another company\'s invoice', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($this->user)->get("/invoices/{$invoice->id}/edit")->assertForbidden();
});
