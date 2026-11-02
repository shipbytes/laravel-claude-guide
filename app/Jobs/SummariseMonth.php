<?php

namespace App\Jobs;

use App\AI\MonthlySummaryWriter;
use App\Models\Company;
use App\Models\MonthlySummary;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// One company's monthly summary, on the queue. The SDK already retries a 429 or a 5xx twice
// with backoff inside one attempt; this job's attempts are the slow retries on top of that,
// minutes apart, for the overloaded hour rather than the overloaded second.
class SummariseMonth implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> seconds before attempts 2, 3 and 4 */
    public array $backoff = [60, 300, 900];

    public function __construct(public Company $company, public CarbonImmutable $month) {}

    public function uniqueId(): string
    {
        return "{$this->company->id}:{$this->month->format('Y-m')}";
    }

    public function handle(MonthlySummaryWriter $writer): void
    {
        // The month column is a date cast; a Carbon instance here matches what the cast stored.
        $summary = MonthlySummary::firstOrCreate(
            ['company_id' => $this->company->id, 'month' => $this->month->startOfMonth()],
        );

        $summary->update(['status' => 'ready', 'summary' => $writer->write($this->company, $this->month)]);
    }

    public function failed(\Throwable $e): void
    {
        MonthlySummary::where('company_id', $this->company->id)->whereDate('month', $this->month->startOfMonth())
            ->update(['status' => 'failed']);
    }
}
