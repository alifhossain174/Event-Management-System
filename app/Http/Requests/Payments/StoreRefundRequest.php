<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('refund', $this->route('payment'));
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'amount' => ['required', 'decimal:0,4', 'gt:0', 'max:999999999999999.9999'],
            'refunded_at' => ['required', 'date'],
            'payment_allocation_id' => ['nullable', Rule::exists('payment_allocations', 'id')],
            'channel' => ['nullable', 'string', 'max:100'],
            'external_reference' => ['nullable', 'string', 'max:191'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
