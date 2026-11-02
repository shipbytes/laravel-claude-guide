<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\OverdueReminder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class SendOverdueReminders extends Command
{
    protected $signature = 'invoices:remind {--dry-run : List the invoices without sending anything}';

    protected $description = 'Email a reminder for every sent invoice past its due date, at most every three days';

    public function handle(): int
    {
        $overdue = Invoice::query()
            ->with('client')
            ->where('status', InvoiceStatus::Sent)
            ->whereDate('due_at', '<', today())
            ->where(fn (Builder $q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->subDays(3)))
            ->orderBy('due_at')
            ->get();

        foreach ($overdue as $invoice) {
            $this->line("{$invoice->number}: due {$invoice->due_at->toDateString()}, {$invoice->client->email}");

            if ($this->option('dry-run')) {
                continue;
            }

            Notification::route('mail', $invoice->client->email)->notify(new OverdueReminder($invoice));
            $invoice->forceFill(['reminded_at' => now()])->save();
        }

        $this->info($this->option('dry-run') ? "{$overdue->count()} overdue, nothing sent." : "Reminded {$overdue->count()} client(s).");

        return self::SUCCESS;
    }
}
