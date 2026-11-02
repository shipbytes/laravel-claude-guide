<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Mail\ReceiptMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/** Queued: the webhook answers the provider at once, the receipt goes out from a worker. */
class SendReceipt implements ShouldQueue
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice->load('client');

        Mail::to($invoice->client->email)->send(new ReceiptMail($invoice));
    }
}
