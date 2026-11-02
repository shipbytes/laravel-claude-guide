<?php

namespace App\Billing\Actions;

use App\Enums\InvoiceStatus;
use App\Events\InvoicePaid;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a provider payment against an invoice. Idempotent on the provider's reference:
 * providers retry webhooks, and the second delivery must not count the money twice.
 */
class RecordPayment
{
    public function handle(Invoice $invoice, string $reference, int $amount, string $currency, CarbonImmutable $paidAt): Payment
    {
        if ($existing = Payment::query()->where('reference', $reference)->first()) {
            return $existing;
        }

        if ($currency !== $invoice->currency) {
            throw new \DomainException("Payment in {$currency} against an invoice in {$invoice->currency}.");
        }

        if ($invoice->status !== InvoiceStatus::Sent) {
            throw new \DomainException("Invoice is {$invoice->status->value}; only sent invoices take payments.");
        }

        return DB::transaction(function () use ($invoice, $reference, $amount, $currency, $paidAt) {
            $payment = $invoice->payments()->create([
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'provider' => 'paygate',
                'paid_at' => $paidAt,
            ]);

            if ($invoice->amountPaid() >= $invoice->total) {
                $invoice->forceFill(['status' => InvoiceStatus::Paid])->save();
                InvoicePaid::dispatch($invoice);
            }

            return $payment;
        });
    }
}
