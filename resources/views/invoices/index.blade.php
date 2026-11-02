<x-layouts.app title="Invoices">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Invoices</h1>
        <a href="{{ route('invoices.create') }}" class="rounded-md bg-zinc-900 px-3 py-1.5 text-sm text-white">New invoice</a>
    </div>
    <table class="w-full border-collapse overflow-hidden rounded-lg border border-zinc-200 bg-white text-sm">
        <thead class="bg-zinc-100 text-left text-xs uppercase tracking-wide text-zinc-500">
            <tr>
                <th class="px-4 py-2">Number</th>
                <th class="px-4 py-2">Client</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Due</th>
                <th class="px-4 py-2 text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr class="border-t border-zinc-200">
                    <td class="px-4 py-2 font-mono"><a href="{{ route('invoices.show', $invoice) }}" class="hover:underline">{{ $invoice->number ?? 'Draft #'.$invoice->id }}</a></td>
                    <td class="px-4 py-2">{{ $invoice->client->name }}</td>
                    <td class="px-4 py-2">{{ $invoice->status->label() }}</td>
                    <td class="px-4 py-2">{{ $invoice->due_at?->toFormattedDateString() ?? '—' }}</td>
                    <td class="px-4 py-2 text-right font-mono">{{ $invoice->currency }} {{ number_format($invoice->total / 100, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
