@php use App\Billing\Money; @endphp
<x-layouts.app :title="$invoice->number ?? 'Draft invoice'">
    <div class="mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-xl font-semibold">{{ $invoice->number ?? 'Draft' }}</h1>
            <p class="text-sm text-zinc-500">{{ $invoice->client->name }} · <span data-status>{{ $invoice->status->label() }}</span>
                @if ($invoice->issued_at) · issued {{ $invoice->issued_at->toFormattedDateString() }}, due {{ $invoice->due_at->toFormattedDateString() }} @endif
            </p>
        </div>
        <div class="flex gap-2 text-sm">
            @can('update', $invoice)
                <a href="{{ route('invoices.edit', $invoice) }}" class="rounded-md border border-zinc-300 px-3 py-1.5">Edit</a>
            @endcan
            @can('send', $invoice)
                <form method="post" action="{{ route('invoices.send', $invoice) }}">@csrf<button class="rounded-md bg-zinc-900 px-3 py-1.5 text-white">Send</button></form>
            @endcan
            @can('void', $invoice)
                <form method="post" action="{{ route('invoices.void', $invoice) }}">@csrf<button class="rounded-md border border-red-300 px-3 py-1.5 text-red-700">Void</button></form>
            @endcan
            @can('delete', $invoice)
                <form method="post" action="{{ route('invoices.destroy', $invoice) }}">@csrf @method('delete')<button class="rounded-md border border-zinc-300 px-3 py-1.5 text-zinc-600">Delete</button></form>
            @endcan
        </div>
    </div>

    @error('invoice') <p class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

    <table class="w-full rounded-lg border border-zinc-200 bg-white text-sm">
        <thead class="bg-zinc-100 text-left text-xs uppercase tracking-wide text-zinc-500">
            <tr><th class="px-4 py-2">Description</th><th class="px-4 py-2 text-right">Qty</th><th class="px-4 py-2 text-right">Unit</th><th class="px-4 py-2 text-right">Tax</th><th class="px-4 py-2 text-right">Net</th></tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr class="border-t border-zinc-200">
                    <td class="px-4 py-2">{{ $line->description }}</td>
                    <td class="px-4 py-2 text-right">{{ $line->quantity + 0 }}</td>
                    <td class="px-4 py-2 text-right font-mono">{{ Money::format($line->unit_price, $invoice->currency) }}</td>
                    <td class="px-4 py-2 text-right">{{ $line->tax_rate + 0 }}%</td>
                    <td class="px-4 py-2 text-right font-mono">{{ Money::format((int) round($line->unit_price * $line->quantity), $invoice->currency) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="border-t border-zinc-200 text-right">
            <tr><td colspan="4" class="px-4 py-1 text-zinc-500">Subtotal</td><td class="px-4 py-1 font-mono" data-subtotal>{{ Money::format($invoice->subtotal, $invoice->currency) }}</td></tr>
            @if ($invoice->discount > 0)
                <tr><td colspan="4" class="px-4 py-1 text-zinc-500">Discount {{ $invoice->discount_percent + 0 }}%</td><td class="px-4 py-1 font-mono">−{{ Money::format($invoice->discount, $invoice->currency) }}</td></tr>
            @endif
            <tr><td colspan="4" class="px-4 py-1 text-zinc-500">Tax</td><td class="px-4 py-1 font-mono" data-tax>{{ Money::format($invoice->tax, $invoice->currency) }}</td></tr>
            <tr class="font-semibold"><td colspan="4" class="px-4 py-2">Total</td><td class="px-4 py-2 font-mono" data-total>{{ Money::format($invoice->total, $invoice->currency) }}</td></tr>
            @if ($approx)
                <tr><td colspan="4" class="px-4 py-1 text-xs text-zinc-500">In {{ $invoice->company->currency }}, at today's rate</td><td class="px-4 py-1 font-mono text-xs text-zinc-500" data-approx>≈ {{ $approx }}</td></tr>
            @endif
            @if ($invoice->payments->isNotEmpty())
                <tr><td colspan="4" class="px-4 py-1 text-zinc-500">Paid</td><td class="px-4 py-1 font-mono" data-paid>{{ Money::format($invoice->amountPaid(), $invoice->currency) }}</td></tr>
            @endif
        </tfoot>
    </table>

    @if ($invoice->notes) <p class="mt-4 text-sm text-zinc-600">{{ $invoice->notes }}</p> @endif
</x-layouts.app>
