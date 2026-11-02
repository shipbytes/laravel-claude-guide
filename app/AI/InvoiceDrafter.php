<?php

namespace App\AI;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Company;

// Turns a plain-English request into an InvoiceDraft for one company. The company's own
// data (clients, defaults, today's date) goes in the system prompt; the user's text is the
// only user message. The API fills the InvoiceDraft schema; this class does the rest.
final class InvoiceDrafter
{
    public function __construct(private Client $claude) {}

    public function draft(Company $company, string $request): InvoiceDraft
    {
        $model = config('claude.models.default');

        try {
            $message = $this->claude->messages->create(
                model: $model,
                maxTokens: 2048,
                system: $this->systemPrompt($company),
                messages: [['role' => 'user', 'content' => $request]],
                // Extraction needs no deliberation: low effort is faster and cheaper, and the
                // schema, not the reasoning, is what makes the output correct.
                outputConfig: ['format' => InvoiceDraft::class, 'effort' => 'low'],
            );
        } catch (APIStatusException|APIConnectionException $e) {
            throw new DraftFailed('Claude could not draft the invoice: '.$e->getMessage(), previous: $e);
        }

        if ($message->stopReason !== 'end_turn') {
            throw new DraftFailed("Claude stopped early ({$message->stopReason}); the draft is incomplete.");
        }

        Usage::record('draft', $model, $message->usage, $company, $message->stopReason);

        $draft = $message->parsedOutput();

        if (! $draft instanceof InvoiceDraft) {
            throw new DraftFailed('Claude returned something that is not an invoice draft.');
        }

        return $draft;
    }

    private function systemPrompt(Company $company): string
    {
        $clients = $company->clients()->orderBy('name')->pluck('name')
            ->map(fn (string $name) => "- {$name}")
            ->implode("\n");
        // The decimal cast gives "20.00"; the prompt reads better as "20".
        $taxRate = (float) $company->default_tax_rate;

        return <<<PROMPT
            You draft invoices for {$company->name} from a short description written by one of its staff.
            Fill the schema from the description and nothing else. Do not invent lines, prices or clients.

            Known clients (use the name exactly as written; omit the client if the description names none of them):
            {$clients}

            Company defaults: currency {$company->currency}, tax rate {$taxRate}% on every line unless the description gives another rate.
            Today is {$company->freshTimestamp()->toDateString()}.

            Prices are per unit in major currency units (write 150.00 for one hundred and fifty). Hours, days and items are quantities; a flat fee is quantity 1.
            Put anything you could not map into `unresolved`, one short sentence per problem, so the person can fix it. Omit it when everything mapped.
            PROMPT;
    }
}
