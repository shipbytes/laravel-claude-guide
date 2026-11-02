<?php

// Everything the app knows about the Claude API lives here. The key comes from the
// environment; the models and their prices are decisions, so they are in version control.
return [

    'api_key' => env('ANTHROPIC_API_KEY'),

    'models' => [
        // Reasoning, drafting, the assistant: the current Opus.
        'default' => 'claude-opus-5-5',
        // Classification and other short, high-volume calls: the current Haiku.
        'fast' => 'claude-haiku-5-5',
    ],

    // USD per million tokens, from https://claude.com/pricing#api on 3 November 2026.
    // Used to put a price on every call the app makes; see app/AI/Cost.php.
    'prices' => [
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00],
        'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50],
    ],

    // Seconds before a request is abandoned, and how many times the SDK retries a 429,
    // a 5xx or a dropped connection before giving up. Streaming requests ignore the timeout.
    'timeout' => 60,
    'max_retries' => 2,
];
