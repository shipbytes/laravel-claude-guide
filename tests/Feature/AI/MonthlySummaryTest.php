<?php

use Anthropic\Core\Exceptions\RateLimitException;
use App\AI\MonthlySummaryWriter;
use App\Jobs\SummariseMonth;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\MonthlySummary;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Tests\Support\FakeClaude;

beforeEach(function () {
    $this->travelTo('2026-11-03 09:00:00');
    $this->company = Company::factory()->create(['name' => 'Northwind', 'currency' => 'EUR']);
    $acme = Client::factory()->for($this->company)->create(['name' => 'Acme GmbH']);
    Invoice::factory()->for($this->company)->for($acme)->sent()->create(['number' => 'INV-2026-0040', 'issued_at' => '2026-10-05', 'due_at' => '2026-10-19', 'total' => 120000, 'currency' => 'EUR']);
    Invoice::factory()->for($this->company)->for($acme)->sent()->create(['number' => 'INV-2026-0041', 'issued_at' => '2026-10-20', 'due_at' => '2026-11-03', 'total' => 60000, 'currency' => 'EUR']);
    $this->october = CarbonImmutable::parse('2026-10-01');
});

it('writes the month from facts the app computed and stores it', function () {
    $claude = FakeClaude::bind(
        FakeClaude::tokenCount(310),
        FakeClaude::stream("October brought two invoices for Acme GmbH, €1,800.00 in all.\n\nINV-2026-0040 is overdue.", inputTokens: 310, outputTokens: 40),
    );

    (new SummariseMonth($this->company, $this->october))->handle(app(MonthlySummaryWriter::class));

    $summary = MonthlySummary::sole();
    expect($summary->status)->toBe('ready')
        ->and($summary->month->toDateString())->toBe('2026-10-01')
        ->and($summary->summary)->toContain('INV-2026-0040 is overdue.');

    // First the token count, then the streamed request, both carrying the facts.
    expect($claude->requests)->toHaveCount(2)
        ->and($claude->requests[0]['uri'])->toBe('https://api.anthropic.com/v1/messages/count_tokens');
    $facts = $claude->requests[1]['body']['messages'][0]['content'];
    expect($facts)->toContain('Month: October 2026')
        ->toContain('Invoices issued this month: 2, totalling €1,800.00')
        ->toContain('Acme GmbH: €1,800.00 across 2 invoice(s)')
        ->toContain('Overdue at the end of the month: 1.')
        ->toContain('INV-2026-0040 for Acme GmbH, €1,200.00, due 2026-10-19')
        ->and($claude->requests[1]['body']['stream'])->toBeTrue()
        ->and($claude->requests[1]['body']['max_tokens'])->toBe(16000);

    expect(ClaudeCall::sole()->feature)->toBe('summary');
});

it('retries slowly on top of the SDK\'s fast retries, then marks the summary failed', function () {
    $job = new SummariseMonth($this->company, $this->october);

    expect($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([60, 300, 900])
        ->and($job->uniqueId())->toBe("{$this->company->id}:2026-10");

    FakeClaude::bind(FakeClaude::tokenCount(310), FakeClaude::error(429, 'rate_limit_error', 'Too many requests'));
    MonthlySummary::create(['company_id' => $this->company->id, 'month' => '2026-10-01']);

    expect(fn () => $job->handle(app(MonthlySummaryWriter::class)))->toThrow(RateLimitException::class);

    $job->failed(new RuntimeException('gave up'));
    expect(MonthlySummary::sole()->status)->toBe('failed');
});

it('queues one job per company on the first of the month', function () {
    Bus::fake();
    Company::factory()->create();

    $this->artisan('summaries:generate')->expectsOutputToContain('Queued 2 summary job(s) for October 2026.');

    Bus::assertDispatchedTimes(SummariseMonth::class, 2);
    Bus::assertDispatched(SummariseMonth::class, fn (SummariseMonth $job) => $job->company->is($this->company) && $job->month->toDateString() === '2026-10-01');
});

it('packs every company into one Message Batch instead, at half price', function () {
    $other = Company::factory()->create(['name' => 'Initech']);
    $claude = FakeClaude::bind(FakeClaude::batch('msgbatch_01', 'in_progress', processing: 2));

    $this->artisan('summaries:generate', ['--batch' => true])->expectsOutputToContain('Batch msgbatch_01 sent with 2 request(s)');

    $requests = $claude->lastRequest()['body']['requests'];
    expect($claude->lastRequest()['uri'])->toBe('https://api.anthropic.com/v1/messages/batches')
        ->and($requests)->toHaveCount(2)
        ->and($requests[0]['custom_id'])->toBe("summary-{$this->company->id}-2026-10")
        ->and($requests[0]['params']['model'])->toBe('claude-opus-5-5')
        ->and($requests[0]['params']['messages'][0]['content'])->toContain('Company: Northwind')
        ->and($requests[1]['params']['messages'][0]['content'])->toContain('Company: Initech')
        ->and(MonthlySummary::where('status', 'pending')->where('batch_id', 'msgbatch_01')->count())->toBe(2);

    // Collecting too early changes nothing.
    FakeClaude::bind(FakeClaude::batch('msgbatch_01', 'in_progress', processing: 2));
    $this->artisan('summaries:collect', ['batch' => 'msgbatch_01'])->assertFailed();
    expect(MonthlySummary::where('status', 'pending')->count())->toBe(2);

    // Collecting when it has ended stores each summary and records the halved cost.
    FakeClaude::bind(
        FakeClaude::batch('msgbatch_01', 'ended', succeeded: 1, errored: 1),
        FakeClaude::batchResults([
            ['custom_id' => "summary-{$this->company->id}-2026-10", 'result' => ['type' => 'succeeded', 'message' => FakeClaude::text('A quiet October for Northwind.', 300, 50)]],
            ['custom_id' => "summary-{$other->id}-2026-10", 'result' => ['type' => 'errored', 'error' => ['type' => 'invalid_request', 'message' => 'nope']]],
        ]),
    );
    $this->artisan('summaries:collect', ['batch' => 'msgbatch_01'])->expectsOutputToContain('Stored 1 summary(ies)');

    expect($this->company->summaries()->sole()->summary)->toBe('A quiet October for Northwind.')
        ->and($other->summaries()->sole()->status)->toBe('failed');
    $call = ClaudeCall::sole();
    expect($call->batch)->toBeTrue()
        ->and((float) $call->cost)->toBe(0.0011); // (300 × $4 + 50 × $20) / 2, per million
});

it('shows the summaries page', function () {
    MonthlySummary::create(['company_id' => $this->company->id, 'month' => '2026-10-01', 'status' => 'ready', 'summary' => "**October** was busy.\n\n- INV-2026-0040"]);
    $user = User::factory()->for($this->company)->create();

    $this->actingAs($user)->get(route('summaries.index'))
        ->assertSee('October 2026')
        ->assertSee('<strong>October</strong>', escape: false)
        ->assertSee('<li>INV-2026-0040</li>', escape: false);
});
