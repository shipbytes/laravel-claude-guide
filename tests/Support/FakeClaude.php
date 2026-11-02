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

    /** A structured-output response: the JSON the schema asked for, inside one text block. */
    public static function json(array $data, int $inputTokens = 10, int $outputTokens = 5): array
    {
        return self::text(json_encode($data), $inputTokens, $outputTokens);
    }

    /** An error the API returns with a status, in the API's error envelope. */
    public static function error(int $status, string $type, string $message): array
    {
        return ['__status' => $status, 'type' => 'error', 'error' => ['type' => $type, 'message' => $message]];
    }

    public function queue(array $body): void
    {
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
