<?php

namespace App\AI;

use Anthropic\Beta\Messages\BetaUsage;
use Anthropic\Messages\Usage as ApiUsage;
use App\Models\ClaudeCall;
use App\Models\Company;

// Every call the app makes is recorded here with its cost, so `php artisan claude:usage`
// and the per-company figures are facts from a table, not estimates from a dashboard.
final class Usage
{
    public static function record(string $feature, string $model, ApiUsage|BetaUsage $usage, ?Company $company = null, ?string $stopReason = null, bool $batch = false): ClaudeCall
    {
        return ClaudeCall::create([
            'company_id' => $company?->id,
            'feature' => $feature,
            'model' => $model,
            'input_tokens' => $usage->inputTokens,
            'cache_read_input_tokens' => $usage->cacheReadInputTokens ?? 0,
            'cache_creation_input_tokens' => $usage->cacheCreationInputTokens ?? 0,
            'output_tokens' => $usage->outputTokens,
            'cost' => Cost::of($model, $usage, $batch),
            'stop_reason' => $stopReason,
            'batch' => $batch,
            'created_at' => now(),
        ]);
    }
}
