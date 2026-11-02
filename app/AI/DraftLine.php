<?php

namespace App\AI;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;
use App\Billing\Money;

// One line of a drafted invoice, as the model returns it. Prices come back in major units
// (150.00, not 15000) because that is how people write them; the app converts.
final class DraftLine implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'What was sold, as it should appear on the invoice')]
    public string $description;

    #[Constrained(description: 'How many; 1 for a flat fee')]
    public float $quantity;

    #[Constrained(description: 'Price per unit in major currency units, e.g. 150.00; 0 if the text gives no price')]
    public float $unitPrice;

    #[Constrained(description: 'Tax rate in percent for this line. Omit this field to use the company default')]
    public ?float $taxRate = null;

    public function unitPriceMinor(string $currency): int
    {
        return (int) round($this->unitPrice * 10 ** Money::decimals($currency));
    }
}
