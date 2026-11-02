<?php

namespace App\AI;

use Anthropic\Lib\Tools\BetaRunnableTool;
use App\Billing\Money;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;

// The three things the assistant can look up, all read-only and all scoped to one company.
// Every query starts from $company's relations, so there is no input the model could send
// that reaches another company's rows: the boundary is the query, not the prompt.
final class AssistantTools
{
    public function __construct(private Company $company) {}

    /** @return list<BetaRunnableTool> */
    public function all(): array
    {
        return [$this->listInvoices(), $this->getInvoice(), $this->findClients()];
    }

    private function listInvoices(): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'list_invoices',
                'description' => 'List the company\'s invoices, newest first, with totals. Filter by status, client name or overdue only. Returns at most 50.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['draft', 'sent', 'paid', 'void'], 'description' => 'Only invoices in this status'],
                        'client' => ['type' => 'string', 'description' => 'Only invoices whose client name contains this text'],
                        'overdue_only' => ['type' => 'boolean', 'description' => 'Only sent invoices past their due date'],
                    ],
                ],
            ],
            run: function (array $input): string {
                $query = $this->company->invoices()->with('client')->latest('id');

                if (isset($input['status'])) {
                    $status = InvoiceStatus::tryFrom($input['status'])
                        ?? throw new \InvalidArgumentException("Unknown status [{$input['status']}]; use draft, sent, paid or void.");
                    $query->where('status', $status);
                }
                if (! empty($input['client'])) {
                    $query->whereRelation('client', 'name', 'like', '%'.$input['client'].'%');
                }
                if (! empty($input['overdue_only'])) {
                    $query->where('status', InvoiceStatus::Sent)->whereDate('due_at', '<', today());
                }

                $invoices = $query->limit(50)->get();
                $outstanding = [];
                foreach ($invoices as $invoice) {
                    if ($invoice->status === InvoiceStatus::Sent) {
                        $outstanding[$invoice->currency] = ($outstanding[$invoice->currency] ?? 0) + $invoice->total - $invoice->amountPaid();
                    }
                }

                return json_encode([
                    'count' => $invoices->count(),
                    'outstanding_total' => array_map(fn (int $minor, string $currency) => Money::format($minor, $currency), $outstanding, array_keys($outstanding)),
                    'invoices' => $invoices->map(fn (Invoice $i) => $this->summary($i))->all(),
                ]);
            },
        );
    }

    private function getInvoice(): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'get_invoice',
                'description' => 'One invoice in full, by its number: lines, payments and dates.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['number' => ['type' => 'string', 'description' => 'The invoice number, e.g. INV-2026-0005']],
                    'required' => ['number'],
                ],
            ],
            run: function (array $input): string {
                $invoice = $this->company->invoices()->with('client', 'lines', 'payments')->where('number', $input['number'])->first();

                if ($invoice === null) {
                    return json_encode(['error' => "No invoice numbered {$input['number']} for this company."]);
                }

                return json_encode([
                    ...$this->summary($invoice),
                    'lines' => $invoice->lines->map(fn ($line) => [
                        'description' => $line->description,
                        'quantity' => (float) $line->quantity,
                        'unit_price' => Money::format($line->unit_price, $invoice->currency),
                        'tax_rate' => (float) $line->tax_rate,
                    ])->all(),
                    'payments' => $invoice->payments->map(fn ($p) => [
                        'amount' => Money::format($p->amount, $invoice->currency),
                        'paid_at' => $p->paid_at?->toDateString(),
                        'provider' => $p->provider,
                    ])->all(),
                    'notes' => $invoice->notes,
                ]);
            },
        );
    }

    private function findClients(): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'find_clients',
                'description' => 'Clients whose name contains the text, with how many invoices each has and what they owe. Empty text lists every client.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string', 'description' => 'Part of a client name; empty for all']],
                ],
            ],
            run: function (array $input): string {
                $clients = $this->company->clients()->with('invoices')
                    ->when(! empty($input['query']), fn ($q) => $q->where('name', 'like', '%'.$input['query'].'%'))
                    ->orderBy('name')->get();

                return json_encode($clients->map(function ($client) {
                    $sent = $client->invoices->where('status', InvoiceStatus::Sent);

                    return [
                        'name' => $client->name,
                        'email' => $client->email,
                        'country' => $client->country,
                        'invoices' => $client->invoices->count(),
                        'outstanding' => Money::format((int) $sent->sum(fn (Invoice $i) => $i->total - $i->amountPaid()), $client->currency),
                    ];
                })->all());
            },
        );
    }

    private function summary(Invoice $invoice): array
    {
        return [
            'number' => $invoice->number,
            'client' => $invoice->client->name,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'issued_at' => $invoice->issued_at?->toDateString(),
            'due_at' => $invoice->due_at?->toDateString(),
            'overdue' => $invoice->status === InvoiceStatus::Sent && $invoice->due_at?->isPast(),
            'total' => Money::format($invoice->total, $invoice->currency),
            'paid' => Money::format($invoice->amountPaid(), $invoice->currency),
            'outstanding' => Money::format($invoice->status === InvoiceStatus::Sent ? $invoice->total - $invoice->amountPaid() : 0, $invoice->currency),
        ];
    }
}
