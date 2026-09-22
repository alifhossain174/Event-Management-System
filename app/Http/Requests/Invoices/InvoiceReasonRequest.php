<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

final class InvoiceReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');
        $ability = $this->routeIs('events.invoices.credit') ? 'credit' : 'cancel';

        return $invoice && $invoice->event_id === $this->route('event')->getKey() && $this->user()->can($ability, $invoice);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
