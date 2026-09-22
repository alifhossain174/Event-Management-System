<?php

namespace App\Http\Requests\Invoices;

use App\Models\Invoice;

final class StoreInvoiceRequest extends InvoiceRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Invoice::class, $this->route('event')]);
    }
}
