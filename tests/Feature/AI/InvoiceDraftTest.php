<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Tests\Support\FakeClaude;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Northwind', 'currency' => 'EUR', 'default_tax_rate' => 20]);
    $this->user = User::factory()->for($this->company)->create();
    $this->acme = Client::factory()->for($this->company)->create(['name' => 'Acme GmbH']);
});

$acmeDraft = fn () => [
    'clientName' => 'Acme GmbH',
    'currency' => 'EUR',
    'discountPercent' => 0,
    'lines' => [
        ['description' => 'Consulting', 'quantity' => 12, 'unitPrice' => 150.0, 'taxRate' => null],
        ['description' => 'Setup fee', 'quantity' => 1, 'unitPrice' => 300.0, 'taxRate' => 0],
    ],
    'notes' => null,
    'unresolved' => null,
];

it('prefills the form from the draft', function () use ($acmeDraft) {
    FakeClaude::bind(FakeClaude::json($acmeDraft()));

    $this->actingAs($this->user)
        ->post(route('invoices.draft'), ['description' => 'Invoice Acme for 12 hours of consulting at 150 an hour and a 300 setup fee'])
        ->assertRedirect(route('invoices.create'))
        ->assertSessionHas('status')
        ->assertSessionHasInput('client_id', $this->acme->id)
        ->assertSessionHasInput('currency', 'EUR')
        ->assertSessionHasInput('lines', [
            ['description' => 'Consulting', 'quantity' => 12.0, 'unit_price' => 15000, 'tax_rate' => 20.0],
            ['description' => 'Setup fee', 'quantity' => 1.0, 'unit_price' => 30000, 'tax_rate' => 0.0],
        ]);

    // And the form really shows it.
    $this->actingAs($this->user)->get(route('invoices.create'))
        ->assertSee('Draft filled in from your description')
        ->assertSee('value="Consulting"', escape: false)
        ->assertSee('value="15000"', escape: false);
});

it('asks for the draft schema, with the company context in the system prompt', function () use ($acmeDraft) {
    $claude = FakeClaude::bind(FakeClaude::json($acmeDraft()));

    $this->actingAs($this->user)->post(route('invoices.draft'), ['description' => 'Invoice Acme for 12 hours of consulting at 150']);

    $body = $claude->lastRequest()['body'];
    expect($body['model'])->toBe('claude-opus-5-5')
        ->and($body['output_config']['effort'])->toBe('low')
        ->and($body['output_config']['format']['type'])->toBe('json_schema')
        ->and($body['output_config']['format']['schema']['required'])->toContain('currency', 'lines')
        ->and($body['output_config']['format']['schema']['properties']['currency']['enum'])->toBe(['EUR', 'USD', 'GBP', 'JPY'])
        ->and($body['output_config']['format']['schema']['properties']['lines']['items']['properties'])->toHaveKeys(['description', 'quantity', 'unitPrice'])
        ->and($body['system'])->toContain('- Acme GmbH')
        ->and($body['system'])->toContain('currency EUR, tax rate 20%')
        ->and($body['messages'])->toBe([['role' => 'user', 'content' => 'Invoice Acme for 12 hours of consulting at 150']]);
});

it('leaves the client empty and says why when the text names nobody it knows', function (?string $clientName) use ($acmeDraft) {
    FakeClaude::bind(FakeClaude::json([...$acmeDraft(), 'clientName' => $clientName, 'unresolved' => 'No client named Globex is known.']));

    $this->actingAs($this->user)
        ->post(route('invoices.draft'), ['description' => 'Invoice Globex for 12 hours of consulting at 150'])
        ->assertRedirect(route('invoices.create'))
        ->assertSessionHas('draft_unresolved', 'No client named Globex is known.');

    // assertSessionHasInput cannot assert a null, so read the flashed input directly.
    expect(session()->getOldInput('client_id'))->toBeNull()
        ->and(session()->getOldInput('lines'))->toHaveCount(2);
})->with(['null, as the schema allows' => [null], 'an empty string, as the model sometimes sends' => ['']]);

it('never matches a client of another company', function () use ($acmeDraft) {
    Client::factory()->create(['name' => 'Initech']); // someone else's client
    FakeClaude::bind(FakeClaude::json([...$acmeDraft(), 'clientName' => 'Initech']));

    $this->actingAs($this->user)
        ->post(route('invoices.draft'), ['description' => 'Invoice Initech for 12 hours of consulting at 150'])
        ->assertRedirect(route('invoices.create'));

    expect(session()->getOldInput('client_id'))->toBeNull();
});

it('tells the person when the API is down instead of a 500 page', function () {
    FakeClaude::bind(FakeClaude::error(529, 'overloaded_error', 'Overloaded'));

    $this->actingAs($this->user)
        ->post(route('invoices.draft'), ['description' => 'Invoice Acme for 12 hours of consulting at 150'])
        ->assertRedirect(route('invoices.create'))
        ->assertSessionHas('draft_error')
        ->assertSessionHasInput('description', 'Invoice Acme for 12 hours of consulting at 150');
});

it('treats a truncated reply as a failure', function () use ($acmeDraft) {
    $response = FakeClaude::json($acmeDraft());
    $response['stop_reason'] = 'max_tokens';
    FakeClaude::bind($response);

    $this->actingAs($this->user)
        ->post(route('invoices.draft'), ['description' => 'Invoice Acme for 12 hours of consulting at 150'])
        ->assertSessionHas('draft_error');
});

it('validates the description before spending a token', function () {
    $claude = FakeClaude::bind();

    $this->actingAs($this->user)
        ->post(route('invoices.draft'), ['description' => 'short'])
        ->assertSessionHasErrors('description');

    expect($claude->requests)->toBe([]);
});

it('requires a login', function () {
    $this->post(route('invoices.draft'), ['description' => 'Invoice Acme for 12 hours of consulting at 150'])
        ->assertRedirect(route('login'));
});
