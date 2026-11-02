<?php

namespace App\Console\Commands;

use Anthropic\Client;
use Anthropic\Messages\Batches\MessageBatchSucceededResult;
use App\AI\MonthlySummaryWriter;
use App\AI\Usage;
use App\Models\MonthlySummary;
use Illuminate\Console\Command;

// Fetches a finished Message Batch and stores each company's summary. Safe to run early:
// a batch still processing is reported and nothing changes.
class CollectSummaries extends Command
{
    protected $signature = 'summaries:collect {batch : The message batch id}';

    protected $description = 'Store the summaries from a finished Message Batch';

    public function handle(Client $claude): int
    {
        $batch = $claude->messages->batches->retrieve($this->argument('batch'));

        if ($batch->processingStatus !== 'ended') {
            $this->warn("Batch {$batch->id} is {$batch->processingStatus}: {$batch->requestCounts->processing} still processing.");

            return self::FAILURE;
        }

        $stored = 0;
        foreach ($claude->messages->batches->resultsStream($batch->id) as $item) {
            $summary = MonthlySummary::where('batch_id', $batch->id)->get()
                ->first(fn (MonthlySummary $s) => $item->customID === "summary-{$s->company_id}-{$s->month->format('Y-m')}");

            if ($summary === null) {
                $this->warn("No pending summary for {$item->customID}.");

                continue;
            }

            if (! $item->result instanceof MessageBatchSucceededResult) {
                $summary->update(['status' => 'failed']);
                $this->error("{$item->customID}: {$item->result->type}");

                continue;
            }

            $message = $item->result->message;
            Usage::record('summary', $message->model, $message->usage, $summary->company, $message->stopReason, batch: true);
            $summary->update(['status' => 'ready', 'summary' => MonthlySummaryWriter::text($message)]);
            $stored++;
        }

        $this->info("Stored {$stored} summary(ies) from batch {$batch->id}.");

        return self::SUCCESS;
    }
}
