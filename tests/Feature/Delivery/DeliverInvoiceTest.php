<?php

use App\Jobs\DeliverInvoice;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme Studio']);
    $this->user = User::factory()->for($this->company)->create();
    $this->client = Client::factory()->for($this->company)->create(['name' => 'Northwind Traders', 'email' => 'billing@northwind.test']);
});

it('queues the delivery when an invoice is sent', function () {
    Bus::fake();

    $draft = Invoice::factory()->for($this->company)->for($this->client)
        ->has(InvoiceLine::factory(), 'lines')->create();

    $this->actingAs($this->user)->post("/invoices/{$draft->id}/send")->assertRedirect();

    Bus::assertDispatched(DeliverInvoice::class, fn (DeliverInvoice $job) => $job->invoice->is($draft));
});

it('writes the PDF to the invoices disk and mails it to the client', function () {
    Storage::fake('invoices');
    Mail::fake();

    $invoice = Invoice::factory()->for($this->company)->for($this->client)->sent()
        ->has(InvoiceLine::factory()->state(['description' => 'Brand guidelines', 'quantity' => 1, 'unit_price' => 2500_00, 'tax_rate' => 20]), 'lines')
        ->create(['number' => 'INV-2026-0003']);
    $invoice->recalculate();

    (new DeliverInvoice($invoice))->handle();

    Storage::disk('invoices')->assertExists("{$this->company->id}/INV-2026-0003.pdf");
    expect(Storage::disk('invoices')->get("{$this->company->id}/INV-2026-0003.pdf"))->toStartWith('%PDF-');

    Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
        $mail->assertHasSubject('Invoice INV-2026-0003 from Acme Studio')
            ->assertSeeInHtml('€3,000.00')
            // An Attachment::fromStorageDisk() returned from attachments() is attached as data, so
            // assertHasAttachmentFromStorageDisk() (which only sees attachFromStorageDisk() calls) would
            // never match. Assert on the bytes, with the name and MIME type the mail gives them.
            ->assertHasAttachedData(Storage::disk('invoices')->get("{$this->company->id}/INV-2026-0003.pdf"), 'INV-2026-0003.pdf', ['mime' => 'application/pdf']);

        return $mail->hasTo('billing@northwind.test') && $mail->invoice->is($invoice);
    });
});

it('renders the mail without sending anything', function () {
    // render() builds the whole message, attachments included, so the file has to exist.
    Storage::fake('invoices')->put('x.pdf', '%PDF-1.4 stub');
    $invoice = Invoice::factory()->for($this->company)->for($this->client)->sent()->create(['number' => 'INV-2026-0004', 'total' => 99_00]);

    $html = (new InvoiceMail($invoice, 'x.pdf'))->render();

    expect($html)->toContain('Hello Northwind Traders')->toContain('INV-2026-0004')->toContain('€99.00');
});
