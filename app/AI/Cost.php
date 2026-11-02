<?php

namespace App\AI;

use Anthropic\Beta\Messages\BetaUsage;
use Anthropic\Messages\Usage;

// Turns a response's token counts into dollars, using the price table in config/claude.php.
// The API bills four kinds of token: fresh input, input read from the prompt cache (a tenth
// of the price), input written to the cache (a quarter more), and output.
final class Cost
{
    // The beta namespace (the tool runner lives there) has its own Usage class with the same fields.
    public static function of(string $model, Usage|BetaUsage $usage, bool $batch = false): float
    {
        $prices = config("claude.prices.{$model}");

        if ($prices === null) {
            throw new \InvalidArgumentException("No price configured for model [{$model}].");
        }

        $dollars = (
            $usage->inputTokens * $prices['input']
            + ($usage->cacheReadInputTokens ?? 0) * $prices['cache_read']
            + ($usage->cacheCreationInputTokens ?? 0) * $prices['cache_write']
            + $usage->outputTokens * $prices['output']
        ) / 1_000_000;

        // The Batch API charges half of every rate.
        return $batch ? $dollars / 2 : $dollars;
    }

    // Six decimals: a Haiku call costs millionths of a dollar and would round to nothing at four.
    public static function format(float $dollars): string
    {
        return '$'.number_format($dollars, 6);
    }
}
