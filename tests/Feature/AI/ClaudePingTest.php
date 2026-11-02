<?php

use Anthropic\Core\Exceptions\AuthenticationException;
use Tests\Support\FakeClaude;

it('prints the reply and what it cost', function () {
    FakeClaude::bind(FakeClaude::text('A container that resolves classes.', inputTokens: 20, outputTokens: 8));

    $this->artisan('claude:ping')
        ->expectsOutputToContain('A container that resolves classes.')
        ->expectsTable(
            ['model', 'stop reason', 'input tokens', 'output tokens', 'cost'],
            [['claude-opus-5-5', 'end_turn', 20, 8, '$0.000240']], // 20 × $4 + 8 × $20, per million
        )
        ->assertSuccessful();
});

it('sends the prompt to the configured model with the key', function () {
    $claude = FakeClaude::bind(FakeClaude::text('ok'));

    $this->artisan('claude:ping', ['prompt' => 'Say ok']);

    $request = $claude->lastRequest();
    expect($request['method'])->toBe('POST')
        ->and($request['uri'])->toBe('https://api.anthropic.com/v1/messages')
        ->and($request['headers']['authorization'])->toBe('Bearer test-key')
        ->and($request['headers']['anthropic-version'])->toBe('2026-10-21')
        ->and($request['body']['model'])->toBe('claude-opus-5-5')
        ->and($request['body']['max_tokens'])->toBe(1024)
        ->and($request['body']['messages'])->toBe([['role' => 'user', 'content' => 'Say ok']]);
});

it('uses the fast model when asked', function () {
    $claude = FakeClaude::bind(FakeClaude::text('ok', model: 'claude-haiku-5-5'));

    $this->artisan('claude:ping', ['--model' => 'claude-haiku-5-5'])->assertSuccessful();

    expect($claude->lastRequest()['body']['model'])->toBe('claude-haiku-5-5');
});

it('surfaces an API error instead of swallowing it', function () {
    FakeClaude::bind(FakeClaude::error(401, 'authentication_error', 'invalid x-api-key'));

    $this->artisan('claude:ping');
})->throws(AuthenticationException::class);
