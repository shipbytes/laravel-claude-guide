<?php

namespace App\AI;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

// What the request classifier answers: is this text something to draft an invoice from?
final class Verdict implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'True if the text describes goods or services to invoice someone for')]
    public bool $isInvoiceRequest;

    #[Constrained(description: 'One short sentence, for the person, saying what the text is instead when it is not an invoice request')]
    public string $reason;
}
