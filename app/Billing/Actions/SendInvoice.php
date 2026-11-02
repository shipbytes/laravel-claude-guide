<?php

namespace App\Billing\Actions;

use App\Billing\DueDate;
use App\Billing\InvoiceNumber;
use App\Enums\InvoiceStatus;
use App\Events\InvoiceSent;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sending turns a draft into a real invoice: it gets the next number for the company's
 * year, an issue date, a due date, and the Sent status. The number is assigned inside a
 * transaction with the lookup locked, so two people sending at once cannot share one.
 */
class SendInvoice
{
    public function handle(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Sent)) {
            throw new \DomainException("Invoice is {$invoice->status->value}, not a draft.");
        }

        if ($invoice->lines()->doesntExist()) {
            throw new \DomainException('An invoice needs at least one line before it is sent.');
        }

        $company = $invoice->company;
        $today = CarbonImmutable::today();

        DB::transaction(function () use ($invoice, $company, $today) {
            $last = Invoice::lastNumber($company, $company->invoice_prefix, $today->year);

            $invoice->recalculate();
            $invoice->forceFill([
                'number' => InvoiceNumber::next($last, $company->invoice_prefix, $today->year),
                'status' => InvoiceStatus::Sent,
                'issued_at' => $today,
                'due_at' => DueDate::compute($today, $company->payment_terms_days),
            ])->save();
        });

        InvoiceSent::dispatch($invoice);

        return $invoice->refresh();
    }
}
