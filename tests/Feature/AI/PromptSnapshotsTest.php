<?php

use App\AI\MonthlySummaryWriter;
use App\Jobs\SummariseMonth;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Support\FakeClaude;

// Every prompt the app sends, frozen as a snapshot. A change to any of them shows up as a
// diff in the pull request, which is the review a prompt change deserves. Snapshots live
// in tests/.pest/snapshots; `pest --update-snapshots` accepts a deliberate change.
beforeEach(function () {
    $this->travelTo('2026-11-03 09:00:00');
    $this->company = Company::factory()->create(['name' => 'Northwind', 'currency' => 'EUR', 'default_tax_rate' => 20]);
    $this->user = User::factory()->for($this->company)->create();
    $this->client = Client::factory()->for($this->company)->create(['name' => 'Acme GmbH']);
});

it('sends the invoice drafter prompt', function () {
    $claude = FakeClaude::bind(
        FakeClaude::json(['isInvoiceRequest' => true, 'reason' => '']),
        FakeClaude::json(['currency' => 'EUR', 'discountPercent' => 0, 'lines' => [['description' => 'x', 'quantity' => 1, 'unitPrice' => 1]]]),
    );

    $this->actingAs($this->user)->post(route('invoices.draft'), ['description' => 'Invoice Acme GmbH for 2 hours at 100']);

    expect($claude->requests[0]['body']['system'])->toMatchSnapshot()      // the classifier
        ->and($claude->requests[1]['body']['system'])->toMatchSnapshot()   // the drafter
        ->and($claude->requests[1]['body']['output_config'])->toMatchSnapshot();
});

it('sends the reminder prompt', function () {
    $invoice = Invoice::factory()->for($this->company)->for($this->client)->sent()->create([
        'number' => 'INV-2026-0042', 'issued_at' => '2026-10-10', 'due_at' => '2026-10-24', 'currency' => 'EUR', 'total' => 120000,
    ]);
    $claude = FakeClaude::bind(FakeClaude::stream('Subject: x'));

    $this->actingAs($this->user)->get(route('invoices.reminder', $invoice))->streamedContent();

    expect($claude->lastRequest()['body']['system'])->toMatchSnapshot();
});

it('sends the assistant prompt and tools', function () {
    $claude = FakeClaude::bind(FakeClaude::text('ok'));

    $this->actingAs($this->user)->post(route('assistant.store'), ['question' => 'Anything overdue?']);

    expect($claude->lastRequest()['body']['system'])->toMatchSnapshot()
        ->and($claude->lastRequest()['body']['tools'])->toMatchSnapshot();
});

it('sends the monthly summary prompt', function () {
    Invoice::factory()->for($this->company)->for($this->client)->sent()->create(['number' => 'INV-2026-0040', 'issued_at' => '2026-10-05', 'due_at' => '2026-10-19', 'total' => 120000, 'currency' => 'EUR']);
    $claude = FakeClaude::bind(FakeClaude::tokenCount(10), FakeClaude::stream('October.'));

    (new SummariseMonth($this->company, CarbonImmutable::parse('2026-10-01')))->handle(app(MonthlySummaryWriter::class));

    expect($claude->requests[1]['body']['system'])->toMatchSnapshot()
        ->and($claude->requests[1]['body']['messages'][0]['content'])->toMatchSnapshot();
});
