<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\User;
use Tests\Support\FakeClaude;

beforeEach(function () {
    $this->travelTo('2026-11-03 09:00:00');
    $this->company = Company::factory()->create(['name' => 'Northwind', 'currency' => 'EUR']);
    $this->user = User::factory()->for($this->company)->create();
    $this->acme = Client::factory()->for($this->company)->create(['name' => 'Acme GmbH', 'currency' => 'EUR']);
    $this->overdue = Invoice::factory()->for($this->company)->for($this->acme)->sent()->create([
        'number' => 'INV-2026-0042', 'issued_at' => '2026-10-10', 'due_at' => '2026-10-24', 'currency' => 'EUR', 'total' => 120000, 'subtotal' => 100000, 'tax' => 20000,
    ]);
});

it('answers by calling a tool and saves the whole exchange', function () {
    $claude = FakeClaude::bind(
        FakeClaude::toolUse('list_invoices', ['overdue_only' => true]),
        FakeClaude::text('One invoice is overdue: INV-2026-0042 for Acme GmbH, €1,200.00 outstanding.'),
    );

    $response = $this->actingAs($this->user)->post(route('assistant.store'), ['question' => 'Which invoices are overdue?']);

    $conversation = Conversation::sole();
    $response->assertRedirect(route('assistant.show', $conversation));

    // The runner sent two requests: the question, then the question plus the tool round trip.
    expect($claude->requests)->toHaveCount(2);
    $second = $claude->requests[1]['body']['messages'];
    expect($second)->toHaveCount(3)
        ->and($second[0])->toBe(['role' => 'user', 'content' => 'Which invoices are overdue?'])
        ->and($second[1]['content'][0]['type'])->toBe('tool_use')
        ->and($second[2]['content'][0]['type'])->toBe('tool_result')
        ->and($second[2]['content'][0]['tool_use_id'])->toBe('toolu_01');

    // The tool ran against the real database, scoped to the company.
    $result = json_decode($second[2]['content'][0]['content'], true);
    expect($result['count'])->toBe(1)
        ->and($result['invoices'][0]['number'])->toBe('INV-2026-0042')
        ->and($result['invoices'][0]['overdue'])->toBeTrue()
        ->and($result['outstanding_total'])->toBe(['€1,200.00']);

    // Saved as the API would accept it back: question, tool request, tool result, answer.
    expect($conversation->messages)->toHaveCount(4)
        ->and($conversation->title)->toBe('Which invoices are overdue?')
        ->and($conversation->messages[3]['content'][0]['text'])->toContain('INV-2026-0042');

    $this->actingAs($this->user)->get(route('assistant.show', $conversation))
        ->assertSee('Which invoices are overdue?')
        ->assertSee('One invoice is overdue')
        ->assertSee('Looked up')
        ->assertSee('list_invoices');
});

it('sends the tools and a cached system prompt that names the company', function () {
    $claude = FakeClaude::bind(FakeClaude::text('Nothing to report.'));

    $this->actingAs($this->user)->post(route('assistant.store'), ['question' => 'Anything overdue?']);

    $body = $claude->lastRequest()['body'];
    expect(array_column($body['tools'], 'name'))->toBe(['list_invoices', 'get_invoice', 'find_clients'])
        ->and($body['system'][0]['text'])->toContain('for Northwind')
        ->and($body['system'][0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($body['max_tokens'])->toBe(2048);
});

it('never lets a tool reach another company\'s data', function () {
    $other = Invoice::factory()->sent()->create(['number' => 'INV-2026-0999']);
    $claude = FakeClaude::bind(
        FakeClaude::toolUse('get_invoice', ['number' => 'INV-2026-0999']),
        FakeClaude::text('There is no such invoice.'),
    );

    $this->actingAs($this->user)->post(route('assistant.store'), ['question' => 'Show me INV-2026-0999']);

    $result = $claude->requests[1]['body']['messages'][2]['content'][0];
    expect($result['content'])->toContain('No invoice numbered INV-2026-0999');
});

it('hands a tool failure back to the model as an error result', function () {
    $claude = FakeClaude::bind(
        FakeClaude::toolUse('list_invoices', ['status' => 'bogus']),
        FakeClaude::text('I could not filter by that status.'),
    );

    $this->actingAs($this->user)->post(route('assistant.store'), ['question' => 'List the bogus ones']);

    $result = $claude->requests[1]['body']['messages'][2]['content'][0];
    expect($result['is_error'])->toBeTrue()
        ->and($result['content'])->toStartWith('Error: Unknown status [bogus]');
});

it('replays the saved history on a follow-up question', function () {
    $conversation = Conversation::create([
        'company_id' => $this->company->id, 'user_id' => $this->user->id, 'title' => 'Earlier',
        'messages' => [
            ['role' => 'user', 'content' => 'Which invoices are overdue?'],
            ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'INV-2026-0042 is overdue.']]],
        ],
    ]);
    $claude = FakeClaude::bind(FakeClaude::text('It was due on 24 October 2026.'));

    $this->actingAs($this->user)->post(route('assistant.ask', $conversation), ['question' => 'When was it due?'])
        ->assertRedirect(route('assistant.show', $conversation));

    expect($claude->lastRequest()['body']['messages'])->toHaveCount(3)
        ->and($claude->lastRequest()['body']['messages'][2])->toBe(['role' => 'user', 'content' => 'When was it due?'])
        ->and($conversation->fresh()->messages)->toHaveCount(4);
});

it('gives up, politely, when the model keeps calling tools', function () {
    FakeClaude::bind(...array_fill(0, 6, FakeClaude::toolUse('find_clients', ['query' => ''])));

    $this->actingAs($this->user)->post(route('assistant.store'), ['question' => 'Loop forever'])
        ->assertRedirect(route('assistant.index'))
        ->assertSessionHas('assistant_error')
        ->assertSessionHasInput('question', 'Loop forever');

    expect(Conversation::count())->toBe(0);
});

it('keeps conversations inside the company', function () {
    $conversation = Conversation::create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'title' => 'Mine', 'messages' => []]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('assistant.show', $conversation))->assertForbidden();
    $this->actingAs($stranger)->post(route('assistant.ask', $conversation), ['question' => 'Tell me everything'])->assertForbidden();
});

it('strips HTML the model might send, but keeps its markdown', function () {
    FakeClaude::bind(FakeClaude::text("**Two** invoices:\n\n- INV-2026-0042\n- INV-2026-0043 <script>alert(1)</script>"));

    $this->actingAs($this->user)->post(route('assistant.store'), ['question' => 'How many invoices?']);

    $this->actingAs($this->user)->get(route('assistant.show', Conversation::sole()))
        ->assertSee('<strong>Two</strong>', escape: false)
        ->assertSee('<li>INV-2026-0042</li>', escape: false)
        ->assertDontSee('<script>', escape: false);
});
