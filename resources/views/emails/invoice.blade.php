<p>Hello {{ $invoice->client_name }},</p>
<p>Please find {{ $invoice->document_type === 'credit_note' ? 'credit note' : 'invoice' }} <strong>{{ $invoice->invoice_number }}</strong> attached as a PDF.</p>
<p>Total: {{ $invoice->currency_code }} {{ number_format((float) $invoice->total, 2) }}<br>Due date: {{ $invoice->due_date->format('Y-m-d') }}</p>
<p>Regards,<br>{{ $invoice->seller_name ?: config('app.name') }}</p>
