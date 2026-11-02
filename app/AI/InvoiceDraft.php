<?php

namespace App\AI;

use Anthropic\Core\Attributes\Required;
use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Support\Collection;

// The shape the model must fill in. The SDK turns this class into a JSON schema and the API
// guarantees the reply matches it, so the app never parses prose and never sees a missing key.
final class InvoiceDraft implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'The client, copied exactly from the list of known clients. Omit this field if the text names none of them')]
    public ?string $clientName = null;

    #[Required(enum: ['EUR', 'USD', 'GBP', 'JPY'])]
    #[Constrained(description: 'Invoice currency; the company default unless the text says otherwise')]
    public string $currency;

    #[Constrained(description: 'Whole-invoice discount in percent; 0 when none is mentioned')]
    public float $discountPercent = 0;

    #[Constrained(description: 'The invoice lines, in the order they were described', itemClass: DraftLine::class, minItems: 1)]
    public array $lines;

    #[Constrained(description: 'Notes for the invoice footer. Omit this field unless the text asks for some')]
    public ?string $notes = null;

    #[Constrained(description: 'Anything in the text that could not be turned into a field: an unknown client, a line without a price, an unsupported currency. One short sentence each. Omit this field when everything mapped')]
    public ?string $unresolved = null;

    /**
     * The draft as the invoice form expects it, so the form can be prefilled through old()
     * input. Client names are matched case-insensitively against the company's clients.
     *
     * @param  Collection<int, Client>  $clients
     */
    public function toFormInput(Company $company, Collection $clients): array
    {
        // The model sometimes sends "" where the schema allows null; both mean "no client".
        $name = trim((string) $this->clientName);
        $client = $name === ''
            ? null
            : $clients->first(fn (Client $c) => strcasecmp($c->name, $name) === 0);

        return [
            'client_id' => $client?->id,
            'currency' => $this->currency,
            'discount_percent' => $this->discountPercent,
            'notes' => $this->notes,
            'lines' => array_map(fn (DraftLine $line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPriceMinor($this->currency),
                'tax_rate' => $line->taxRate ?? (float) $company->default_tax_rate,
            ], $this->lines),
        ];
    }
}
