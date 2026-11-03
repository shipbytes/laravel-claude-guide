<?php

namespace App\Console\Commands;

use App\AI\Cost;
use App\Models\ClaudeCall;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

// What the app has spent, per feature and model, from the claude_calls table.
class ClaudeUsage extends Command
{
    protected $signature = 'claude:usage {--days=30 : Look back this many days} {--company= : One company id}';

    protected $description = 'Token and cost totals per feature from the claude_calls table';

    public function handle(): int
    {
        $rows = ClaudeCall::query()
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            ->when($this->option('company'), fn (Builder $q, string $id) => $q->where('company_id', $id))
            ->groupBy('feature', 'model')
            ->orderBy('feature')
            ->select('feature', 'model', DB::raw('count(*) as calls'), DB::raw('sum(input_tokens) as input'),
                DB::raw('sum(cache_read_input_tokens) as cache_read'), DB::raw('sum(output_tokens) as output'), DB::raw('sum(cost) as cost'))
            ->get();

        $this->table(
            ['feature', 'model', 'calls', 'input', 'cache read', 'output', 'cost'],
            $rows->map(fn (ClaudeCall $r) => [$r->feature, $r->model, $r->calls, $r->input, $r->cache_read, $r->output, Cost::format((float) $r->cost)])
                ->push(['total', '', $rows->sum('calls'), $rows->sum('input'), $rows->sum('cache_read'), $rows->sum('output'), Cost::format((float) $rows->sum('cost'))])
                ->all(),
        );

        return self::SUCCESS;
    }
}
