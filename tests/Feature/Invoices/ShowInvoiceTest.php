<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
});

it('shows the lines and the formatted totals', function () {
    $invoice = Invoice::factory()->for($this->company)
        ->for(Client::factory()->for($this->company))
        ->has(InvoiceLine::factory()->state(['description' => 'Two days of consulting', 'quantity' => 2, 'unit_price' => 600_00, 'tax_rate' => 20]), 'lines')
        ->create(['currency' => 'EUR']);
    $invoice->recalculate();

    $this->actingAs($this->user)
        ->get("/invoices/{$invoice->id}")
        ->assertOk()
        ->assertSeeInOrder(['Two days of consulting', '€1,200.00', '€240.00', '€1,440.00']);
});

it('offers edit and send on a draft, void on a sent invoice', function () {
    $draft = Invoice::factory()->for($this->company)->for(Client::factory()->for($this->company))->create();
    $sent = Invoice::factory()->for($this->company)->for(Client::factory()->for($this->company))->sent()->create();

    $this->actingAs($this->user)->get("/invoices/{$draft->id}")
        ->assertSee('Edit')->assertSee('Send')->assertDontSee('Void');

    $this->actingAs($this->user)->get("/invoices/{$sent->id}")
        ->assertDontSee('>Edit<', false)->assertDontSee('>Send<', false)->assertSee('Void');
});

it('is forbidden for another company\'s user', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($this->user)->get("/invoices/{$invoice->id}")->assertForbidden();
});

it('is not found for an id that does not exist', function () {
    $this->actingAs($this->user)->get('/invoices/999999')->assertNotFound();
});
