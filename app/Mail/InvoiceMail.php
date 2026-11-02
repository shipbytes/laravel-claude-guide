<?php

namespace App\Mail;

use App\Billing\Money;
use App\Models\Invoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    public function __construct(public Invoice $invoice, public string $pdfPath) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Invoice {$this->invoice->number} from {$this->invoice->company->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice', with: [
            'total' => Money::format($this->invoice->total, $this->invoice->currency),
            'due' => $this->invoice->due_at->toFormattedDateString(),
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('invoices', $this->pdfPath)->as("{$this->invoice->number}.pdf")->withMime('application/pdf'),
        ];
    }
}
