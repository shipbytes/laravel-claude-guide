<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Notifications\OverdueReminder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-24 09:00');
    $this->company = Company::factory()->create();
});

function invoiceDueOn(Company $company, string $due, string $email, array $attributes = []): Invoice
{
    return Invoice::factory()->for($company)->for(Client::factory()->for($company)->state(['email' => $email]))
        ->sent()->create(['due_at' => $due, ...$attributes]);
}

it('reminds the clients of overdue invoices and remembers that it did', function () {
    $overdue = invoiceDueOn($this->company, '2026-10-20', 'late@client.test', ['number' => 'INV-2026-0010']);
    invoiceDueOn($this->company, '2026-10-30', 'ontime@client.test');
    invoiceDueOn($this->company, '2026-10-24', 'duetoday@client.test');   // due today is not overdue

    $this->artisan('invoices:remind')
        ->expectsOutputToContain('INV-2026-0010: due 2026-10-20, late@client.test')
        ->expectsOutput('Reminded 1 client(s).')
        ->assertSuccessful();

    Notification::assertSentOnDemand(OverdueReminder::class, fn (OverdueReminder $n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'late@client.test' && $n->invoice->is($overdue));
    Notification::assertCount(1);

    expect($overdue->refresh()->reminded_at->toDateTimeString())->toBe('2026-10-24 09:00:00');
});

it('waits three days between reminders', function () {
    $invoice = invoiceDueOn($this->company, '2026-10-01', 'late@client.test', ['reminded_at' => '2026-10-22 09:00']);

    $this->artisan('invoices:remind')->expectsOutput('Reminded 0 client(s).');
    Notification::assertNothingSent();

    $this->travel(2)->days();
    $this->artisan('invoices:remind')->expectsOutput('Reminded 1 client(s).');
    Notification::assertSentOnDemand(OverdueReminder::class);
});

it('leaves paid and voided invoices alone', function () {
    Invoice::factory()->for($this->company)->for(Client::factory()->for($this->company))->paid()->create(['due_at' => '2026-10-01']);
    Invoice::factory()->for($this->company)->for(Client::factory()->for($this->company))->sent()->create(['due_at' => '2026-10-01', 'status' => 'void']);

    $this->artisan('invoices:remind')->expectsOutput('Reminded 0 client(s).');
    Notification::assertNothingSent();
});

it('lists without sending in dry-run mode', function () {
    invoiceDueOn($this->company, '2026-10-20', 'late@client.test', ['number' => 'INV-2026-0011']);

    $this->artisan('invoices:remind --dry-run')
        ->expectsOutputToContain('INV-2026-0011')
        ->expectsOutput('1 overdue, nothing sent.');

    Notification::assertNothingSent();
    expect(Invoice::sole()->reminded_at)->toBeNull();
});

it('writes a reminder that says how late the invoice is', function () {
    $invoice = invoiceDueOn($this->company, '2026-10-17', 'late@client.test', ['number' => 'INV-2026-0012', 'total' => 450_00]);

    $mail = (new OverdueReminder($invoice))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('Reminder: invoice INV-2026-0012 is 7 days overdue')
        ->and($mail->introLines[0])->toContain('€450.00');
});

it('is scheduled every morning', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('invoices:remind');
});
