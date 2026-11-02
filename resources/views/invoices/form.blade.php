<x-layouts.app :title="$invoice->exists ? 'Edit invoice' : 'New invoice'">
    @php($lines = old('lines', $invoice->exists ? $invoice->lines->map->only('description', 'quantity', 'unit_price', 'tax_rate')->all() : [['description' => '', 'quantity' => 1, 'unit_price' => '', 'tax_rate' => $defaultTaxRate]]))
    <h1 class="mb-6 text-xl font-semibold">{{ $invoice->exists ? 'Edit invoice' : 'New invoice' }}</h1>

    @unless ($invoice->exists)
        {{-- Describe the invoice in a sentence; Claude fills the form below for review. Part 2 of the series. --}}
        <form method="post" action="{{ route('invoices.draft') }}" class="mb-8 rounded-md border border-zinc-200 bg-zinc-50 p-4" id="draft-form">
            @csrf
            <label for="description" class="block text-sm font-medium">Describe the invoice</label>
            <textarea id="description" name="description" rows="2" placeholder="e.g. Invoice Acme for 12 hours of consulting at 150 an hour and a 300 setup fee"
                      class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">{{ old('description') }}</textarea>
            @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            <div class="mt-2 flex items-center gap-3">
                <button type="submit" class="rounded-md border border-zinc-900 px-3 py-1.5 text-sm">Draft with Claude</button>
                @if (session('draft_error')) <p class="text-sm text-red-700" role="alert">{{ session('draft_error') }}</p> @endif
            </div>
            @if (session('draft_unresolved'))
                <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">
                    <span class="font-medium">Could not fill in:</span> {{ session('draft_unresolved') }}
                </p>
            @endif
        </form>
    @endunless
    <form method="post" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" class="space-y-6" id="invoice-form">
        @csrf
        @if ($invoice->exists) @method('put') @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="font-medium">Please fix the following:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="client_id" class="block text-sm font-medium">Client</label>
                <select id="client_id" name="client_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    <option value="">Choose…</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id', $invoice->client_id) == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="currency" class="block text-sm font-medium">Currency</label>
                <select id="currency" name="currency" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    @foreach (['EUR', 'USD', 'GBP', 'JPY'] as $code)
                        <option value="{{ $code }}" @selected(old('currency', $invoice->currency) === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="discount_percent" class="block text-sm font-medium">Discount %</label>
                <input id="discount_percent" name="discount_percent" type="number" step="0.01" min="0" max="100"
                       value="{{ old('discount_percent', $invoice->discount_percent ?? 0) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
            </div>
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-medium">Lines</legend>
            <table class="w-full text-sm" id="lines">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500">
                    <tr><th class="py-1">Description</th><th class="py-1">Qty</th><th class="py-1">Unit price (minor units)</th><th class="py-1">Tax %</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($lines as $i => $line)
                        <tr class="line">
                            <td class="pr-2 py-1"><input name="lines[{{ $i }}][description]" value="{{ $line['description'] }}" aria-label="Description" class="w-full rounded-md border border-zinc-300 px-2 py-1"></td>
                            <td class="pr-2 py-1"><input name="lines[{{ $i }}][quantity]" type="number" step="0.01" value="{{ $line['quantity'] }}" aria-label="Quantity" class="w-20 rounded-md border border-zinc-300 px-2 py-1"></td>
                            <td class="pr-2 py-1"><input name="lines[{{ $i }}][unit_price]" type="number" step="1" value="{{ $line['unit_price'] }}" aria-label="Unit price" class="w-32 rounded-md border border-zinc-300 px-2 py-1"></td>
                            <td class="pr-2 py-1"><input name="lines[{{ $i }}][tax_rate]" type="number" step="0.01" value="{{ $line['tax_rate'] }}" aria-label="Tax rate" class="w-20 rounded-md border border-zinc-300 px-2 py-1"></td>
                            <td class="py-1"><button type="button" class="remove-line text-zinc-500 hover:text-red-600" aria-label="Remove line">×</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="button" id="add-line" class="mt-2 text-sm text-zinc-600 hover:text-zinc-900">+ Add line</button>
        </fieldset>

        <div>
            <label for="notes" class="block text-sm font-medium">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">{{ old('notes', $invoice->notes) }}</textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2 text-white">{{ $invoice->exists ? 'Save draft' : 'Create draft' }}</button>
            <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="rounded-md border border-zinc-300 px-4 py-2">Cancel</a>
        </div>
    </form>
</x-layouts.app>
