<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Tests\Support\FakeClaude;

beforeEach(function () {
    $this->travelTo('2026-11-03 09:00:00');
    $this->company = Company::factory()->create(['name' => 'Northwind']);
    $this->user = User::factory()->for($this->company)->create();
    $this->invoice = Invoice::factory()->for($this->company)->sent()->withLines()->create([
        'number' => 'INV-2026-0042', 'issued_at' => '2026-10-10', 'due_at' => '2026-10-24', 'currency' => 'EUR',
    ]);
});

$email = "Subject: Invoice INV-2026-0042 is overdue\n\nDear client, invoice INV-2026-0042 was due on 24 October.\nKind regards";

/** Decode an event-stream body the way EventSource does and return the text of the delta events. */
function deltaText(string $body): string
{
    $text = '';
    foreach (explode("\n\n", trim($body)) as $event) {
        $lines = explode("\n", $event);
        if (array_shift($lines) !== 'event: delta') {
            continue;
        }
        $text .= implode("\n", array_map(fn (string $line) => substr($line, strlen('data: ')), $lines));
    }

    return $text;
}

it('streams the reminder as server-sent events and finishes with the cost', function () use ($email) {
    FakeClaude::bind(FakeClaude::stream($email, inputTokens: 300, outputTokens: 60));

    $response = $this->actingAs($this->user)->get(route('invoices.reminder', $this->invoice));

    $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
    $body = $response->streamedContent();

    expect($body)->toContain("event: delta\ndata: Subject: ")
        ->toContain("event: delta\ndata: client, ")
        ->toContain('event: done')
        ->toContain('"stopReason":"end_turn"')
        ->toContain('"cost":"$0.002400"'); // 300 × $4 + 60 × $20, per million

    // Joined back together, the deltas are the whole email, newlines included: a newline inside
    // a piece of text travels as a second data: line, which is what the SSE format requires.
    expect(deltaText($body))->toBe($email);
});

it('asks for a stream and puts the invoice in the system prompt', function () use ($email) {
    $claude = FakeClaude::bind(FakeClaude::stream($email));

    $this->actingAs($this->user)->get(route('invoices.reminder', $this->invoice))->streamedContent();

    $request = $claude->lastRequest();
    expect($request['headers']['accept'])->toContain('text/event-stream')
        ->and($request['body']['stream'])->toBeTrue()
        ->and($request['body']['model'])->toBe('claude-opus-5-5')
        ->and($request['body']['system'])->toContain('Northwind')
        ->toContain('Number INV-2026-0042, issued Oct 10, 2026, due Oct 24, 2026 (10 days overdue)')
        ->toContain('Outstanding amount: €')
        ->toContain('Today is Nov 3, 2026')
        ->and($request['body']['messages'])->toBe([['role' => 'user', 'content' => 'Write the reminder email.']]);
});

it('tells the page when the API fails mid-stream, instead of ending silently', function () use ($email) {
    FakeClaude::bind(FakeClaude::stream($email, errorAfter: 2));

    $body = $this->actingAs($this->user)->get(route('invoices.reminder', $this->invoice))->streamedContent();

    expect($body)->toContain("event: delta\ndata: Subject: ")
        ->toContain("event: error\ndata: The reminder could not be written.")
        ->not->toContain('event: done');
});

it('tells the page when the API is down before the stream starts', function () {
    FakeClaude::bind(FakeClaude::error(529, 'overloaded_error', 'Overloaded'));

    $body = $this->actingAs($this->user)->get(route('invoices.reminder', $this->invoice))->streamedContent();

    expect($body)->toContain('event: error')->not->toContain('event: delta');
});

it('refuses a draft invoice: there is nothing to remind about yet', function () {
    $draft = Invoice::factory()->for($this->company)->draft()->create();
    $claude = FakeClaude::bind();

    $this->actingAs($this->user)->get(route('invoices.reminder', $draft))->assertForbidden();

    expect($claude->requests)->toBe([]);
});

it('refuses another company\'s invoice', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('invoices.reminder', $this->invoice))->assertForbidden();
});

it('shows the reminder box only when a reminder makes sense', function () {
    $this->actingAs($this->user)->get(route('invoices.show', $this->invoice))->assertSee('Write with Claude');

    $draft = Invoice::factory()->for($this->company)->draft()->create();
    $this->actingAs($this->user)->get(route('invoices.show', $draft))->assertDontSee('Write with Claude');
});
