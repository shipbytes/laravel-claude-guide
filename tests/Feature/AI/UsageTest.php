<?php

use Anthropic\Messages\Usage;
use App\AI\Cost;
use App\Models\ClaudeCall;
use Tests\Support\FakeClaude;

it('prices the four kinds of token the API bills', function () {
    $usage = new Usage;
    $usage->inputTokens = 1000;
    $usage->outputTokens = 100;
    $usage->cacheReadInputTokens = 5000;
    $usage->cacheCreationInputTokens = 2000;

    // 1000 × $4 + 5000 × $0.20 + 2000 × $5 + 100 × $20, per million = $0.004 + $0.001 + $0.010 + $0.002
    expect(Cost::of('claude-opus-5-5', $usage))->toBe(0.017)
        ->and(Cost::of('claude-opus-5-5', $usage, batch: true))->toBe(0.0085);
});

it('records every call in claude_calls with its cost', function () {
    FakeClaude::bind(FakeClaude::text('pong', inputTokens: 24, outputTokens: 82));

    $this->artisan('claude:ping');

    $call = ClaudeCall::sole();
    expect($call->feature)->toBe('ping')
        ->and($call->model)->toBe('claude-opus-5-5')
        ->and($call->input_tokens)->toBe(24)
        ->and($call->output_tokens)->toBe(82)
        ->and($call->company_id)->toBeNull()
        ->and($call->stop_reason)->toBe('end_turn')
        ->and((float) $call->cost)->toBe(0.001736);
});

it('sums the table per feature in claude:usage', function () {
    FakeClaude::bind(FakeClaude::text('a', 100, 10), FakeClaude::text('b', 200, 20, model: 'claude-haiku-5-5'));
    $this->artisan('claude:ping');
    $this->artisan('claude:ping', ['--model' => 'claude-haiku-5-5']);

    $this->artisan('claude:usage')
        ->expectsTable(['feature', 'model', 'calls', 'input', 'cache read', 'output', 'cost'], [
            ['ping', 'claude-haiku-5-5', 1, 200, 0, 20, '$0.000030'],
            ['ping', 'claude-opus-5-5', 1, 100, 0, 10, '$0.000600'],
            ['total', '', 2, 300, 0, 30, '$0.000630'],
        ]);
});
