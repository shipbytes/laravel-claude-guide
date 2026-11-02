<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/** Renders the PDF to the invoices disk, then mails it to the client. */
class DeliverInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Invoice $invoice) {}

    public function handle(): void
    {
        $invoice = $this->invoice->load('client', 'lines', 'company');
        $path = self::pdfPath($invoice);

        Storage::disk('invoices')->put($path, Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->output());

        Mail::to($invoice->client->email)->send(new InvoiceMail($invoice, $path));
    }

    public static function pdfPath(Invoice $invoice): string
    {
        return "{$invoice->company_id}/{$invoice->number}.pdf";
    }
}
