<?php

namespace App\Billing\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

class VoidInvoice
{
    public function handle(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Void)) {
            throw new \DomainException("A {$invoice->status->value} invoice cannot be voided.");
        }

        $invoice->forceFill(['status' => InvoiceStatus::Void])->save();

        return $invoice;
    }
}
