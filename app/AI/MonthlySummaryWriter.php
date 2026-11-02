<?php

namespace App\AI;

use Anthropic\Client;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\Messages\Message;
use App\Billing\Money;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use Carbon\CarbonImmutable;

// Writes a company's month in prose from facts the app computed: what was issued, paid and
// is overdue, by client and by currency. The model gets numbers, never raw rows, and the
// request streams on the server so a long summary cannot hit an HTTP timeout. The same
// request can be packed into a batch (see SummariseMonths) at half the price.
final class MonthlySummaryWriter
{
    public function __construct(private Client $claude) {}

    public function write(Company $company, CarbonImmutable $month): string
    {
        $model = config('claude.models.default');
        $params = $this->params($company, $month);

        // How big the prompt is, before paying for it. A summary over thousands of invoices
        // would need the facts aggregated further; this logs the number so you would know.
        $count = $this->claude->messages->countTokens(messages: $params['messages'], model: $model, system: $params['system']);

        $accumulator = MessageAccumulator::forMessages();
        foreach ($this->claude->messages->createStream(...['model' => $model, ...$params]) as $event) {
            $accumulator->accumulate($event);
        }
        /** @var Message $message */
        $message = $accumulator->message();

        Usage::record('summary', $model, $message->usage, $company, $message->stopReason);

        if ($message->stopReason !== 'end_turn') {
            throw new \RuntimeException("Summary for {$company->name} stopped early ({$message->stopReason}) after a prompt of {$count->inputTokens} tokens.");
        }

        return self::text($message);
    }

    /** The request without the model, so a batch can carry it verbatim. */
    public function params(Company $company, CarbonImmutable $month): array
    {
        return [
            'maxTokens' => 4096,
            'system' => 'You write a short monthly summary for the owner of a small business, from the figures given. Plain prose with a few short paragraphs; one bulleted list at most. Repeat amounts exactly as given, with their currency. Point out what needs attention: overdue invoices, clients who owe the most, a quiet month. Do not invent anything the figures do not show.',
            'messages' => [['role' => 'user', 'content' => $this->facts($company, $month)]],
        ];
    }

    public static function text(Message $message): string
    {
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }

    private function facts(Company $company, CarbonImmutable $month): string
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $issued = $company->invoices()->with('client')->whereBetween('issued_at', [$start, $end])->get();
        $paidInMonth = $company->invoices()->with('client')->whereHas('payments', fn ($q) => $q->whereBetween('paid_at', [$start, $end]))->get();
        $overdue = $company->invoices()->with('client')->where('status', InvoiceStatus::Sent)->whereDate('due_at', '<', $end)->get();

        $sum = fn ($invoices) => collect($invoices)->groupBy('currency')
            ->map(fn ($group, $currency) => Money::format((int) $group->sum('total'), $currency))->implode(', ') ?: 'nothing';
        $byClient = $issued->groupBy('client.name')
            ->map(fn ($group, $client) => "{$client}: ".$sum($group).' across '.$group->count().' invoice(s)')->values()->implode("\n- ");
        $overdueLines = $overdue->map(fn ($i) => "{$i->number} for {$i->client->name}, ".Money::format($i->total - $i->amountPaid(), $i->currency).", due {$i->due_at->toDateString()}")->implode("\n- ");

        return <<<FACTS
            Company: {$company->name}. Month: {$start->format('F Y')}.

            Invoices issued this month: {$issued->count()}, totalling {$sum($issued)}.
            By client:
            - {$byClient}

            Invoices that received a payment this month: {$paidInMonth->count()}, totalling {$sum($paidInMonth)}.

            Overdue at the end of the month: {$overdue->count()}.
            - {$overdueLines}
            FACTS;
    }
}
