<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

final class EmailInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice && $invoice->event_id === $this->route('event')->getKey() && $this->user()->can('email', $invoice);
    }

    public function rules(): array
    {
        return ['recipient' => ['required', 'email:rfc', 'max:320']];
    }
}
