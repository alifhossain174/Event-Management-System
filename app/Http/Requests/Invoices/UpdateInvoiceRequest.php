<?php

namespace App\Http\Requests\Invoices;

final class UpdateInvoiceRequest extends InvoiceRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice && $invoice->event_id === $this->route('event')->getKey() && $this->user()->can('update', $invoice);
    }
}
