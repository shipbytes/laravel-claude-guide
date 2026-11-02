<?php

namespace App\Mail;

use App\Billing\Money;
use App\Models\Invoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReceiptMail extends Mailable
{
    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Payment received for {$this->invoice->number}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt', with: [
            'total' => Money::format($this->invoice->total, $this->invoice->currency),
        ]);
    }
}
