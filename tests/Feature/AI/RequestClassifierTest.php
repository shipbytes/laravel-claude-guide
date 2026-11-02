<?php

use App\Models\ClaudeCall;
use App\Models\Company;
use App\Models\User;
use Tests\Support\FakeClaude;

beforeEach(function () {
    $this->user = User::factory()->for(Company::factory())->create();
});

it('turns away text that is not an invoice request before Opus is asked', function () {
    $claude = FakeClaude::bind(FakeClaude::json(['isInvoiceRequest' => false, 'reason' => 'It is a greeting.'], inputTokens: 60, outputTokens: 12));

    $this->actingAs($this->user)
        ->post(route('invoices.draft'), ['description' => 'Hello there, how are you today?'])
        ->assertRedirect(route('invoices.create'))
        ->assertSessionHas('draft_error', 'That does not read as an invoice. It is a greeting.');

    // One request, to the fast model, and nothing to Opus.
    expect($claude->requests)->toHaveCount(1)
        ->and($claude->lastRequest()['body']['model'])->toBe('claude-haiku-5-5')
        ->and($claude->lastRequest()['body']['max_tokens'])->toBe(256)
        ->and(ClaudeCall::sole()->feature)->toBe('classify');
});

it('keeps the gate open when the classifier itself fails', function () {
    FakeClaude::bind(
        FakeClaude::error(529, 'overloaded_error', 'Overloaded'),
        FakeClaude::json(['clientName' => null, 'currency' => 'EUR', 'discountPercent' => 0, 'lines' => [['description' => 'Consulting', 'quantity' => 1, 'unitPrice' => 100]]]),
    );

    $this->actingAs($this->user)
        ->post(route('invoices.draft'), ['description' => 'Invoice someone for consulting at 100'])
        ->assertRedirect(route('invoices.create'))
        ->assertSessionHas('status');
});
