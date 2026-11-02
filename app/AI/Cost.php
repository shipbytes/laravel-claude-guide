<?php

namespace App\AI;

use Anthropic\Beta\Messages\BetaUsage;
use Anthropic\Messages\Usage;

// Turns a response's token counts into dollars, using the price table in config/claude.php.
// Cached tokens are billed differently; part 5 extends this when the app starts caching.
final class Cost
{
    // The beta namespace (the tool runner lives there) has its own Usage class with the same fields.
    public static function of(string $model, Usage|BetaUsage $usage): float
    {
        $prices = config("claude.prices.{$model}");

        if ($prices === null) {
            throw new \InvalidArgumentException("No price configured for model [{$model}].");
        }

        return $usage->inputTokens * $prices['input'] / 1_000_000
            + $usage->outputTokens * $prices['output'] / 1_000_000;
    }

    // Six decimals: a Haiku call costs millionths of a dollar and would round to nothing at four.
    public static function format(float $dollars): string
    {
        return '$'.number_format($dollars, 6);
    }
}
