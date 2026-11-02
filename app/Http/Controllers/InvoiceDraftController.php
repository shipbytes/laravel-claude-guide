<?php

namespace App\Http\Controllers;

use App\AI\DraftFailed;
use App\AI\InvoiceDrafter;
use App\AI\RequestClassifier;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// "Describe the invoice" on the new-invoice page. Nothing is saved: the draft comes back as
// old() input, the form shows it prefilled, and the person reviews before creating anything.
class InvoiceDraftController extends Controller
{
    public function __invoke(Request $request, InvoiceDrafter $drafter, RequestClassifier $classifier): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);

        $validated = $request->validate(['description' => ['required', 'string', 'min:10', 'max:2000']]);
        $company = $request->user()->company;

        // Haiku first: a cent of Opus is not spent on text that is not an invoice request.
        $verdict = $classifier->isInvoiceRequest($validated['description']);
        if (! $verdict->isInvoiceRequest) {
            return redirect()->route('invoices.create')
                ->withInput($validated)
                ->with('draft_error', "That does not read as an invoice. {$verdict->reason}");
        }

        try {
            $draft = $drafter->draft($company, $validated['description']);
        } catch (DraftFailed $e) {
            report($e);

            return redirect()->route('invoices.create')
                ->withInput($validated)
                ->with('draft_error', 'Drafting failed. Fill the form by hand, or try again in a moment.');
        }

        return redirect()->route('invoices.create')
            ->withInput($draft->toFormInput($company, $company->clients))
            ->with('status', 'Draft filled in from your description. Check every field before creating the invoice.')
            ->with('draft_unresolved', $draft->unresolved);
    }
}
