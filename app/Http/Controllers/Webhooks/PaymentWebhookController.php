<?php

namespace App\Http\Controllers\Webhooks;

use App\Billing\Actions\RecordPayment;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, RecordPayment $action): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'data.reference' => ['required', 'string', 'max:255'],
            'data.invoice_id' => ['required', 'integer'],
            'data.amount' => ['required', 'integer', 'min:1'],
            'data.currency' => ['required', 'string', 'size:3'],
            'data.paid_at' => ['required', 'date'],
        ])['data'];

        if ($request->input('type') !== 'payment.succeeded') {
            return response()->json(['ignored' => $request->input('type')]);
        }

        $invoice = Invoice::findOrFail($data['invoice_id']);

        try {
            $payment = $action->handle(
                $invoice,
                reference: $data['reference'],
                amount: $data['amount'],
                currency: strtoupper($data['currency']),
                paidAt: CarbonImmutable::parse($data['paid_at']),
            );
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['payment' => $payment->id, 'status' => $invoice->refresh()->status]);
    }
}
