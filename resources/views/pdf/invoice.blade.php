@php use App\Billing\Money; @endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $invoice->number }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#18181b}h1{font-size:20px;margin:0 0 4px}table{width:100%;border-collapse:collapse;margin-top:24px}th,td{padding:6px 8px;border-bottom:1px solid #e4e4e7;text-align:left}td.n,th.n{text-align:right}tfoot td{border:0}</style>
</head>
<body>
<h1>Invoice {{ $invoice->number }}</h1>
<p>{{ $invoice->company->name }} → {{ $invoice->client->name }}<br>
Issued {{ $invoice->issued_at->toFormattedDateString() }}, due {{ $invoice->due_at->toFormattedDateString() }}</p>
<table>
<thead><tr><th>Description</th><th class="n">Qty</th><th class="n">Unit</th><th class="n">Tax</th></tr></thead>
<tbody>
@foreach ($invoice->lines as $line)
<tr><td>{{ $line->description }}</td><td class="n">{{ $line->quantity + 0 }}</td><td class="n">{{ Money::format($line->unit_price, $invoice->currency) }}</td><td class="n">{{ $line->tax_rate + 0 }}%</td></tr>
@endforeach
</tbody>
<tfoot>
<tr><td colspan="3" class="n">Subtotal</td><td class="n">{{ Money::format($invoice->subtotal, $invoice->currency) }}</td></tr>
@if ($invoice->discount > 0)<tr><td colspan="3" class="n">Discount</td><td class="n">−{{ Money::format($invoice->discount, $invoice->currency) }}</td></tr>@endif
<tr><td colspan="3" class="n">Tax</td><td class="n">{{ Money::format($invoice->tax, $invoice->currency) }}</td></tr>
<tr><td colspan="3" class="n"><strong>Total</strong></td><td class="n"><strong>{{ Money::format($invoice->total, $invoice->currency) }}</strong></td></tr>
</tfoot>
</table>
@if ($invoice->notes)<p>{{ $invoice->notes }}</p>@endif
</body>
</html>
