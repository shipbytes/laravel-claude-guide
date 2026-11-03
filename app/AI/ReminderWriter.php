<?php

namespace App\AI;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\Messages\Message;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\TextDelta;
use App\Billing\Money;
use App\Models\Invoice;

// Writes a payment reminder for a sent invoice and streams it as it is written. The
// generator yields each piece of text the moment it arrives and returns the complete
// message at the end, so the caller gets both the live stream and the final usage.
final class ReminderWriter
{
    public function __construct(private Client $claude) {}

    /** @return \Generator<int, string, mixed, Message> */
    public function stream(Invoice $invoice): \Generator
    {
        $model = config('claude.models.default');
        $accumulator = MessageAccumulator::forMessages();

        try {
            $events = $this->claude->messages->createStream(
                model: $model,
                maxTokens: 4096, // the email is short; the thinking before it counts too
                system: $this->systemPrompt($invoice),
                messages: [['role' => 'user', 'content' => 'Write the reminder email.']],
            );

            foreach ($events as $event) {
                $accumulator->accumulate($event);

                // Thinking arrives first, as deltas with no text; only the text is for the page.
                if ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    yield $event->delta->text;
                }
            }
        } catch (APIStatusException|APIConnectionException $e) {
            throw new ReminderFailed('Claude could not write the reminder: '.$e->getMessage(), previous: $e);
        }

        $message = $accumulator->message();

        Usage::record('reminder', $model, $message->usage, $invoice->company, $message->stopReason);

        return $message;
    }

    private function systemPrompt(Invoice $invoice): string
    {
        $invoice->loadMissing('company', 'client');
        $today = $invoice->company->freshTimestamp();
        $days = (int) $invoice->due_at->diffInDays($today, absolute: true);
        $when = $invoice->due_at->isPast()
            ? "{$days} days overdue"
            : ($days === 0 ? 'due today' : "due in {$days} days");
        $outstanding = Money::format($invoice->total - $invoice->amountPaid(), $invoice->currency);
        $paid = $invoice->amountPaid() > 0
            ? Money::format($invoice->amountPaid(), $invoice->currency).' has been paid so far.'
            : 'Nothing has been paid so far.';

        return <<<PROMPT
            You write payment reminder emails on behalf of {$invoice->company->name}, a small business, to its client {$invoice->client->name}.

            The invoice:
            - Number {$invoice->number}, issued {$invoice->issued_at->toFormattedDateString()}, due {$invoice->due_at->toFormattedDateString()} ({$when}).
            - Outstanding amount: {$outstanding}. {$paid}
            - Today is {$today->toFormattedDateString()}.

            Write a short, courteous email in plain text: a subject line first ("Subject: ..."), then the body. State the invoice number, the amount and the due date. Ask for payment or for a date by which it will be made. If it is overdue, say so plainly without threatening anything. Mention that the reminder can be ignored if payment has already been sent. Sign off as {$invoice->company->name}. No markdown, no placeholders in square brackets, nothing but the email.
            PROMPT;
    }
}
