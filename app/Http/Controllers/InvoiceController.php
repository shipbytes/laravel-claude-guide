<?php

namespace App\Http\Controllers;

use App\Billing\Actions\SendInvoice;
use App\Billing\Actions\VoidInvoice;
use App\Billing\ExchangeRates;
use App\Billing\Money;
use App\Billing\RatesUnavailable;
use App\Http\Requests\InvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = $request->user()->company->invoices()
            ->with('client')
            ->latest('id')
            ->paginate(25);

        return view('invoices.index', ['invoices' => $invoices]);
    }

    public function create(Request $request): View
    {
        $company = $request->user()->company;

        return view('invoices.form', [
            'invoice' => new Invoice(['currency' => $company->currency, 'discount_percent' => 0]),
            'clients' => $company->clients()->orderBy('name')->get(),
            'defaultTaxRate' => $company->default_tax_rate,
        ]);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $invoice = DB::transaction(function () use ($request) {
            $invoice = $request->user()->company->invoices()->create($request->safe()->except('lines'));
            $this->syncLines($invoice, $request->validated('lines'));

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice created.');
    }

    public function show(Invoice $invoice, ExchangeRates $rates): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load('client', 'lines', 'payments');
        $home = $invoice->company->currency;
        $approx = null;

        if ($invoice->currency !== $home) {
            try {
                $approx = Money::format($rates->convert($invoice->total, $invoice->currency, $home), $home);
            } catch (RatesUnavailable) {
                $approx = 'rate unavailable';
            }
        }

        return view('invoices.show', ['invoice' => $invoice, 'approx' => $approx]);
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        Gate::authorize('update', $invoice);

        return view('invoices.form', [
            'invoice' => $invoice->load('lines'),
            'clients' => $request->user()->company->clients()->orderBy('name')->get(),
            'defaultTaxRate' => $request->user()->company->default_tax_rate,
        ]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        // Authorised by InvoiceRequest::authorize(), before validation.
        DB::transaction(function () use ($request, $invoice) {
            $invoice->update($request->safe()->except('lines'));
            $this->syncLines($invoice, $request->validated('lines'));
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Draft deleted.');
    }

    public function send(Invoice $invoice, SendInvoice $action): RedirectResponse
    {
        Gate::authorize('send', $invoice);

        try {
            $action->handle($invoice);
        } catch (\DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', "Invoice {$invoice->number} sent.");
    }

    public function void(Invoice $invoice, VoidInvoice $action): RedirectResponse
    {
        Gate::authorize('void', $invoice);

        $action->handle($invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice voided.');
    }

    /** Replace the lines wholesale and store the totals. Simpler than diffing, and drafts are small. */
    private function syncLines(Invoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();
        foreach (array_values($lines) as $position => $line) {
            $invoice->lines()->create([...$line, 'position' => $position]);
        }
        $invoice->recalculate();
    }
}
