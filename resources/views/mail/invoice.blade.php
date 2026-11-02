<p>Hello {{ $invoice->client->name }},</p>
<p>Please find invoice <strong>{{ $invoice->number }}</strong> for <strong>{{ $total }}</strong> attached. It is due on {{ $due }}.</p>
<p>Thank you,<br>{{ $invoice->company->name }}</p>
