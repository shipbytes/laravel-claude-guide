<?php

namespace App\AI;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Conversation;

// Answers a question about the company's invoices by letting Claude call the read-only tools
// in AssistantTools. The SDK's tool runner drives the loop: send, run the tools Claude asked
// for, send the results, repeat until Claude answers in text. The whole exchange, tool calls
// included, is saved on the conversation so the next question has the context.
final class Assistant
{
    private const MAX_STEPS = 6;

    public function __construct(private Client $claude) {}

    public function ask(Conversation $conversation, string $question): BetaMessage
    {
        $model = config('claude.models.default');
        $messages = [...$conversation->messages, ['role' => 'user', 'content' => $question]];
        $tools = new AssistantTools($conversation->company);

        $runner = $this->claude->beta->messages->toolRunner(
            maxTokens: 8192,
            messages: $messages,
            model: $model,
            tools: $tools->all(),
            maxIterations: self::MAX_STEPS,
            extraParams: [
                // Cached: identical on every request for the company, and it is the longest
                // part. Part 5 shows what that saves.
                'system' => [['type' => 'text', 'text' => $this->systemPrompt($conversation), 'cacheControl' => ['type' => 'ephemeral']]],
            ],
            thread: false,
        );

        $last = null;
        $steps = 0;

        try {
            foreach ($runner as $message) {
                $steps++;
                $last = $message;

                Usage::record('assistant', $model, $message->usage, $conversation->company, $message->stopReason);
            }
        } catch (APIStatusException|APIConnectionException $e) {
            throw new AssistantFailed('Claude could not answer: '.$e->getMessage(), previous: $e);
        }

        if ($last === null || $last->stopReason !== 'end_turn') {
            throw new AssistantFailed('Claude did not reach an answer in '.self::MAX_STEPS.' steps.');
        }

        // The runner's history is the API's message list: the question, each tool request,
        // each tool result, the answer. Stored as the API would accept it back.
        $conversation->messages = json_decode(json_encode($runner->getParams()['messages']), true);
        $conversation->title ??= mb_strimwidth($question, 0, 80, '…');
        $conversation->save();

        return $last;
    }

    private function systemPrompt(Conversation $conversation): string
    {
        $company = $conversation->company;
        $today = $company->freshTimestamp()->toFormattedDateString();

        return <<<PROMPT
            You are the assistant inside InvoiceMini for {$company->name}, answering questions from its staff about their own invoices and clients.
            Today is {$today}. The company invoices in {$company->currency} by default.

            Use the tools to look things up; never guess a number, a date or a client. If a tool returns nothing, say so. Amounts from the tools are already formatted with their currency; repeat them as given and do not convert between currencies.
            Answer in plain prose, short, with invoice numbers so the person can find them. Use a list only when listing several invoices. You can read but not change anything; if asked to send, create or edit an invoice, say that you cannot and point to the page where they can.
            PROMPT;
    }
}
