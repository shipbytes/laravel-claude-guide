<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->client = Client::factory()->for($this->company)->create();
});

it('voids a sent invoice and keeps its number on record', function () {
    $invoice = Invoice::factory()->for($this->company)->for($this->client)->sent()->create();

    $this->actingAs($this->user)
        ->post("/invoices/{$invoice->id}/void")
        ->assertRedirect("/invoices/{$invoice->id}");

    expect($invoice->refresh())->status->value->toBe('void')->number->not->toBeNull();
});

it('cannot void a paid invoice', function () {
    $invoice = Invoice::factory()->for($this->company)->for($this->client)->paid()->create();

    $this->actingAs($this->user)->post("/invoices/{$invoice->id}/void")->assertForbidden();
});

it('deletes a draft', function () {
    $invoice = Invoice::factory()->for($this->company)->for($this->client)->create();

    $this->actingAs($this->user)
        ->delete("/invoices/{$invoice->id}")
        ->assertRedirect('/invoices');

    $this->assertModelMissing($invoice);
});

it('never deletes a sent invoice', function () {
    $invoice = Invoice::factory()->for($this->company)->for($this->client)->sent()->create();

    $this->actingAs($this->user)->delete("/invoices/{$invoice->id}")->assertForbidden();

    $this->assertModelExists($invoice);
});
