<?php

namespace App\Console\Commands;

use Anthropic\Client;
use App\AI\Cost;
use App\AI\Usage;
use Illuminate\Console\Command;

// The smallest possible round trip: one prompt in, one answer out, and what it cost.
// `php artisan claude:ping` proves the key, the model and the network before anything
// else in the app depends on them.
class ClaudePing extends Command
{
    // A default with a colon in it would be read as the argument's description, so the
    // prompt's default lives in handle() instead of the signature.
    protected $signature = 'claude:ping {prompt? : The message to send} {--model= : Model id, default from config}';

    protected $description = 'Send one message to Claude and print the reply and its cost';

    public function handle(Client $claude): int
    {
        $model = $this->option('model') ?: config('claude.models.default');
        $prompt = $this->argument('prompt') ?? 'In one sentence, what is a Laravel service container?';

        $message = $claude->messages->create(
            model: $model,
            maxTokens: 1024,
            messages: [['role' => 'user', 'content' => $prompt]],
        );

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $this->line($block->text);
            }
        }

        Usage::record('ping', $model, $message->usage, stopReason: $message->stopReason);

        $this->newLine();
        $this->table(['model', 'stop reason', 'input tokens', 'output tokens', 'cost'], [[
            $message->model,
            $message->stopReason,
            $message->usage->inputTokens,
            $message->usage->outputTokens,
            Cost::format(Cost::of($model, $message->usage)),
        ]]);

        return self::SUCCESS;
    }
}
