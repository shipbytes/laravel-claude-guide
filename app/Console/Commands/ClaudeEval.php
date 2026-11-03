<?php

namespace App\Console\Commands;

use App\AI\Cost;
use App\AI\Evals\DraftGrader;
use App\AI\InvoiceDrafter;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Console\Command;

// Runs the invoice-draft eval set against the real API and grades every answer. The one
// number that says whether a prompt change, a model change or a new SDK version made the
// feature better or worse. Costs money: the --budget stops it before it costs much.
class ClaudeEval extends Command
{
    protected $signature = 'claude:eval {set=invoice-drafts : A file in evals/} {--model= : Override the model} {--budget=0.50 : Stop when this many dollars have been spent} {--min=90 : Fail below this pass rate, in percent} {--save : Write the results to evals/results/}';

    protected $description = 'Run an eval set against the API and report the pass rate and the cost';

    public function handle(InvoiceDrafter $drafter): int
    {
        $set = json_decode(file_get_contents(base_path("evals/{$this->argument('set')}.json")), true);
        $model = $this->option('model') ?: config('claude.models.default');
        config(['claude.models.default' => $model]);

        // The fixture company every case assumes. Created for the run and deleted after it,
        // rather than wrapped in a transaction: a rollback would take the usage rows with it.
        $company = Company::create(['name' => 'Northwind', 'currency' => 'EUR', 'default_tax_rate' => 20, 'payment_terms_days' => 14, 'invoice_prefix' => 'INV']);
        foreach (['Acme GmbH', 'Blue Harbor Ltd', 'Kappa Studio'] as $name) {
            Client::create(['company_id' => $company->id, 'name' => $name, 'email' => str($name)->slug().'@example.test', 'country' => 'DE', 'currency' => 'EUR']);
        }

        try {
            [$rows, $passed, $spent] = $this->runCases($set['cases'], $company, $drafter);
        } finally {
            $company->clients()->delete();
            $company->delete();
        }

        $rate = count($rows) ? round(100 * $passed / count($rows), 1) : 0;
        $this->newLine();
        $this->table(['model', 'cases', 'passed', 'pass rate', 'cost'], [[$model, count($rows), $passed, "{$rate}%", Cost::format($spent)]]);

        if ($this->option('save')) {
            $path = base_path("evals/results/{$this->argument('set')}-{$model}-".now()->format('Y-m-d').'.json');
            file_put_contents($path, json_encode(['model' => $model, 'ran_at' => now()->toIso8601String(), 'pass_rate' => $rate, 'cost' => round($spent, 6), 'cases' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
            $this->info("Saved to {$path}");
        }

        return $rate >= (float) $this->option('min') ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0: list<array<string, mixed>>, 1: int, 2: float} rows, passed, dollars spent */
    private function runCases(array $cases, Company $company, InvoiceDrafter $drafter): array
    {
        $rows = [];
        $passed = 0;
        $spent = 0.0;
        $since = ClaudeCall::max('id') ?? 0;

        foreach ($cases as $case) {
            if ($spent >= (float) $this->option('budget')) {
                $this->warn("Budget of \${$this->option('budget')} reached; ".(count($cases) - count($rows)).' case(s) skipped.');
                break;
            }
            try {
                $failed = DraftGrader::grade($drafter->draft($company, $case['text']), $case['expect']);
            } catch (\Throwable $e) {
                $failed = ['threw '.$e::class.': '.mb_strimwidth($e->getMessage(), 0, 80, '…')];
            }
            $cost = (float) ClaudeCall::where('id', '>', $since)->where('feature', 'draft')->sum('cost');
            $since = ClaudeCall::max('id');
            $spent += $cost;
            $passed += $failed === [] ? 1 : 0;
            $rows[] = ['case' => $case['name'], 'pass' => $failed === [], 'cost' => $cost, 'failed' => $failed];
            $this->line(($failed === [] ? '<info>pass</info>' : '<error>FAIL</error>')."  {$case['name']}".($failed ? ': '.implode('; ', $failed) : ''));
        }

        return [$rows, $passed, $spent];
    }
}
