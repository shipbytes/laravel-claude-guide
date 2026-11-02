<?php

namespace Tests\Support;

use Anthropic\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// A PSR-18 client that never touches the network. The SDK sends every request through
// the `transporter` it is given, so a test queues the responses it wants, binds a Client
// built on this fake, and afterwards inspects exactly what the app sent.
final class FakeClaude implements ClientInterface
{
    /** @var list<ResponseInterface> */
    private array $responses = [];

    /** @var list<array{method: string, uri: string, headers: array<string, string>, body: array<string, mixed>}> */
    public array $requests = [];

    /** Bind a Client that answers from this fake and return the fake for assertions. */
    public static function bind(array ...$responses): self
    {
        $fake = new self;
        foreach ($responses as $response) {
            $fake->queue($response);
        }

        app()->instance(Client::class, new Client(
            apiKey: 'test-key',
            requestOptions: ['transporter' => $fake, 'maxRetries' => 0],
        ));

        return $fake;
    }

    /** A complete Messages API response with one text block, as the API would send it. */
    public static function text(string $text, int $inputTokens = 10, int $outputTokens = 5, string $model = 'claude-opus-5-5'): array
    {
        return [
            'id' => 'msg_test_'.substr(md5($text), 0, 12),
            'type' => 'message',
            'role' => 'assistant',
            'model' => $model,
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
        ];
    }

    /** A reply that asks the app to run a tool: one tool_use block and the matching stop reason. */
    public static function toolUse(string $name, array $input, string $id = 'toolu_01', int $inputTokens = 10, int $outputTokens = 5): array
    {
        return [
            ...self::text('', $inputTokens, $outputTokens),
            'id' => 'msg_test_'.$id,
            'content' => [['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input]],
            'stop_reason' => 'tool_use',
        ];
    }

    /** A structured-output response: the JSON the schema asked for, inside one text block. */
    public static function json(array $data, int $inputTokens = 10, int $outputTokens = 5): array
    {
        return self::text(json_encode($data), $inputTokens, $outputTokens);
    }

    /**
     * A streaming response: the text split into pieces and sent as server-sent events in
     * the API's order. An `$errorAfter` count injects an `error` event mid-stream.
     */
    public static function stream(string $text, int $inputTokens = 10, int $outputTokens = 5, string $model = 'claude-opus-5-5', ?int $errorAfter = null): array
    {
        $message = ['id' => 'msg_test_stream', 'type' => 'message', 'role' => 'assistant', 'model' => $model, 'content' => [],
            'stop_reason' => null, 'stop_sequence' => null, 'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => 1]];
        $events = [
            ['message_start', ['type' => 'message_start', 'message' => $message]],
            ['content_block_start', ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]],
        ];
        foreach (preg_split('/(?<=\s)/', $text) as $i => $piece) {
            if ($errorAfter !== null && $i >= $errorAfter) {
                $events[] = ['error', ['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]];
                break;
            }
            $events[] = ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => $piece]]];
        }
        if ($errorAfter === null) {
            $events[] = ['content_block_stop', ['type' => 'content_block_stop', 'index' => 0]];
            $events[] = ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => $outputTokens]]];
            $events[] = ['message_stop', ['type' => 'message_stop']];
        }

        $body = '';
        foreach ($events as [$name, $data]) {
            $body .= "event: {$name}\ndata: ".json_encode($data)."\n\n";
        }

        return ['__sse' => $body];
    }

    /** The answer to a count_tokens request. */
    public static function tokenCount(int $inputTokens): array
    {
        return ['input_tokens' => $inputTokens];
    }

    /** A message batch object, as retrieve() or create() returns it. */
    public static function batch(string $id, string $status = 'in_progress', int $processing = 0, int $succeeded = 0, int $errored = 0): array
    {
        return [
            'id' => $id, 'type' => 'message_batch', 'processing_status' => $status,
            'request_counts' => ['processing' => $processing, 'succeeded' => $succeeded, 'errored' => $errored, 'canceled' => 0, 'expired' => 0],
            'created_at' => '2026-11-03T06:00:00Z', 'expires_at' => '2026-11-04T06:00:00Z',
            'ended_at' => $status === 'ended' ? '2026-11-03T06:20:00Z' : null, 'archived_at' => null, 'cancel_initiated_at' => null,
            'results_url' => $status === 'ended' ? "https://api.anthropic.com/v1/messages/batches/{$id}/results" : null,
        ];
    }

    /** Batch results: one line of JSON per request, each with the message or the error. */
    public static function batchResults(array $items): array
    {
        return ['__raw' => implode("\n", array_map('json_encode', $items))."\n", '__type' => 'application/x-jsonl'];
    }

    /** An error the API returns with a status, in the API's error envelope. */
    public static function error(int $status, string $type, string $message): array
    {
        return ['__status' => $status, 'type' => 'error', 'error' => ['type' => $type, 'message' => $message]];
    }

    public function queue(array $body): void
    {
        if (isset($body['__sse'])) {
            $this->responses[] = new Response(200, ['Content-Type' => 'text/event-stream'], $body['__sse']);

            return;
        }
        if (isset($body['__raw'])) {
            $this->responses[] = new Response(200, ['Content-Type' => $body['__type']], $body['__raw']);

            return;
        }

        $status = $body['__status'] ?? 200;
        unset($body['__status']);

        $this->responses[] = new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => array_change_key_case(array_map(fn (array $values) => implode(', ', $values), $request->getHeaders())),
            'body' => json_decode((string) $request->getBody(), true) ?? [],
        ];

        return array_shift($this->responses)
            ?? throw new \LogicException('FakeClaude has no response left for '.$request->getMethod().' '.$request->getUri());
    }

    public function lastRequest(): array
    {
        return $this->requests[array_key_last($this->requests)] ?? throw new \LogicException('FakeClaude received no request.');
    }
}
