<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class InvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'due_date' => ['required', 'date'],
            'subject' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tax_label' => ['required', 'string', 'max:100'],
            'default_tax_rate' => ['required', 'decimal:0,6', 'gte:0', 'lte:100'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999.9999'],
            'items.*.unit_price' => ['required', 'decimal:0,4', 'gte:0', 'max:999999999999999.9999'],
            'items.*.discount_type' => ['required', Rule::in(['none', 'percentage', 'fixed'])],
            'items.*.discount_value' => ['required', 'decimal:0,6', 'gte:0', 'max:999999999999999.999999'],
            'items.*.tax_rate' => ['required', 'decimal:0,6', 'gte:0', 'lte:100'],
        ];
    }

    public function invoiceAttributes(): array
    {
        return $this->safe()->only(['booking_id', 'due_date', 'subject', 'notes', 'tax_label', 'default_tax_rate']);
    }

    public function items(): array
    {
        return collect($this->validated('items'))->map(fn (array $item) => [
            ...$item, 'tax_label' => $this->validated('tax_label'),
        ])->values()->all();
    }
}
