<?php

namespace App\Console\Commands;

use Anthropic\Client;
use App\AI\MonthlySummaryWriter;
use App\Jobs\SummariseMonth;
use App\Models\Company;
use App\Models\MonthlySummary;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

// Scheduled on the 1st: a summary of last month for every company. One job per company by
// default; --batch packs every company into one Message Batch instead, which costs half
// and comes back within the hour, collected by summaries:collect.
class SummariseMonths extends Command
{
    protected $signature = 'summaries:generate {--month= : YYYY-MM, default last month} {--batch : Send every company in one Message Batch}';

    protected $description = 'Write last month\'s summary for every company, on the queue or through the Batch API';

    public function handle(Client $claude, MonthlySummaryWriter $writer): int
    {
        $month = $this->option('month')
            ? CarbonImmutable::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $companies = Company::all();

        if (! $this->option('batch')) {
            foreach ($companies as $company) {
                SummariseMonth::dispatch($company, $month);
            }
            $this->info("Queued {$companies->count()} summary job(s) for {$month->format('F Y')}.");

            return self::SUCCESS;
        }

        $model = config('claude.models.default');
        $batch = $claude->messages->batches->create(requests: $companies->map(fn (Company $company) => [
            // The SDK's spelling is customID; a customId key passes through unmapped and the API rejects it.
            'customID' => "summary-{$company->id}-{$month->format('Y-m')}",
            'params' => ['model' => $model, ...$writer->params($company, $month)],
        ])->all());

        foreach ($companies as $company) {
            MonthlySummary::updateOrCreate(
                ['company_id' => $company->id, 'month' => $month],
                ['status' => 'pending', 'batch_id' => $batch->id, 'summary' => null],
            );
        }

        $this->info("Batch {$batch->id} sent with {$companies->count()} request(s); run summaries:collect {$batch->id} later.");

        return self::SUCCESS;
    }
}
