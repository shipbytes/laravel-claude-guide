<?php

namespace App\Http\Controllers;

use App\AI\Cost;
use App\AI\ReminderFailed;
use App\AI\ReminderWriter;
use App\Models\Invoice;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

// GET /invoices/{invoice}/reminder answers with server-sent events: a `delta` event per
// piece of text as Claude writes it, then `done` with the stop reason and the cost, or
// `error` if the API failed. The page opens it with EventSource and fills a text box.
class InvoiceReminderController extends Controller
{
    public function __invoke(Invoice $invoice, ReminderWriter $writer): StreamedResponse
    {
        Gate::authorize('remind', $invoice);

        return response()->eventStream(function () use ($invoice, $writer) {
            $stream = $writer->stream($invoice);

            try {
                foreach ($stream as $delta) {
                    yield new StreamedEvent('delta', $delta);
                }
            } catch (ReminderFailed $e) {
                report($e);
                yield new StreamedEvent('error', 'The reminder could not be written. Try again in a moment.');

                return;
            }

            $message = $stream->getReturn();

            yield new StreamedEvent('done', json_encode([
                'stopReason' => $message->stopReason,
                'cost' => Cost::format(Cost::of($message->model, $message->usage)),
            ]));
        }, endStreamWith: null);
    }
}
