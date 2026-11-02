<p>Hello {{ $invoice->client->name }},</p>
<p>We have received your payment of <strong>{{ $total }}</strong> for invoice <strong>{{ $invoice->number }}</strong>. Thank you.</p>
<p>{{ $invoice->company->name }}</p>
