<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

/** A valid form submission for the given client, with overrides merged in. */
function invoicePayload(Client $client, array $overrides = []): array
{
    return array_replace_recursive([
        'client_id' => $client->id,
        'currency' => 'EUR',
        'discount_percent' => 0,
        'notes' => null,
        'lines' => [
            ['description' => 'Design work', 'quantity' => 2, 'unit_price' => 500_00, 'tax_rate' => 20],
            ['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 20_00, 'tax_rate' => 20],
        ],
    ], $overrides);
}

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->client = Client::factory()->for($this->company)->create(['name' => 'Northwind Traders']);
});

it('offers only the company\'s own clients in the form', function () {
    Client::factory()->create(['name' => 'Somebody Else Ltd']);

    $this->actingAs($this->user)
        ->get('/invoices/create')
        ->assertOk()
        ->assertSee('Northwind Traders')
        ->assertDontSee('Somebody Else Ltd');
});

it('creates a draft with its lines and totals computed', function () {
    $response = $this->actingAs($this->user)
        ->post('/invoices', invoicePayload($this->client));

    $invoice = Invoice::sole();

    $response->assertRedirect(route('invoices.show', $invoice));

    expect($invoice)
        ->status->value->toBe('draft')
        ->number->toBeNull()
        ->company_id->toBe($this->company->id)
        ->subtotal->toBe(1020_00)
        ->tax->toBe(204_00)
        ->total->toBe(1224_00)
        ->lines->toHaveCount(2);

    $this->assertDatabaseHas('invoice_lines', ['invoice_id' => $invoice->id, 'description' => 'Hosting', 'position' => 1]);
});

it('applies the discount from the form', function () {
    $this->actingAs($this->user)
        ->post('/invoices', invoicePayload($this->client, ['discount_percent' => 10]));

    expect(Invoice::sole())->discount->toBe(102_00)->total->toBe(1101_60);
});

it('rejects invalid submissions and names the field', function (array $overrides, string $field) {
    $this->actingAs($this->user)
        ->from('/invoices/create')
        ->post('/invoices', invoicePayload($this->client, $overrides))
        ->assertRedirect('/invoices/create')
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('invoices', 0);
})->with([
    'no lines' => [['lines' => null], 'lines'],   // [] would merge into the defaults and change nothing
    'zero quantity' => [['lines' => [0 => ['quantity' => 0]]], 'lines.0.quantity'],
    'negative price' => [['lines' => [1 => ['unit_price' => -1]]], 'lines.1.unit_price'],
    'tax over 100' => [['lines' => [0 => ['tax_rate' => 101]]], 'lines.0.tax_rate'],
    'unknown currency' => [['currency' => 'XYZ'], 'currency'],
    'discount over 100' => [['discount_percent' => 150], 'discount_percent'],
    'missing description' => [['lines' => [0 => ['description' => '']]], 'lines.0.description'],
]);

it('refuses a client that belongs to another company, as if it did not exist', function () {
    $foreign = Client::factory()->create();

    $this->actingAs($this->user)
        ->post('/invoices', invoicePayload($foreign))
        ->assertSessionHasErrors('client_id');

    $this->assertDatabaseCount('invoices', 0);
});

it('requires a login', function () {
    $this->post('/invoices', invoicePayload($this->client))->assertRedirect('/login');
});
