<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Foundation\Http\FormRequest;

final class VendorDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $this->routeIs('events.vendors.invoices.store')
            ? $this->user()->can('uploadInvoice', $assignment)
            : $this->user()->hasPermission('vendors.assign') && $this->user()->can('view', $assignment);
    }

    public function rules(): array
    {
        $common = [
            'title' => ['required', 'string', 'max:180'],
            'file' => ['required', 'file', 'max:'.config('documents.max_kilobytes', 25600)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->routeIs('events.vendors.contracts.store')) {
            return $common + [
                'contract_reference' => ['nullable', 'string', 'max:100'],
                'effective_date' => ['nullable', 'date'],
                'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            ];
        }

        return $common + [
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'amount' => ['required', 'decimal:0,4', 'min:0', 'max:999999999999999.9999'],
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ];
    }
}
