<?php

namespace App\Listeners;

use App\Events\InvoiceSent;
use App\Jobs\DeliverInvoice;

class QueueInvoiceDelivery
{
    public function handle(InvoiceSent $event): void
    {
        DeliverInvoice::dispatch($event->invoice);
    }
}
