<?php

namespace App\Http\Requests\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Payment::class, $this->route('event')]);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'payment_type' => ['required', Rule::in(Payment::TYPES)],
            'amount' => ['required', 'decimal:0,4', 'gt:0', 'max:999999999999999.9999'],
            'received_at' => ['required', 'date'],
            'received_by_user_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'invoice_id' => ['nullable', 'integer', 'min:1'],
            'channel' => ['nullable', 'string', 'max:100'],
            'external_reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'allocations' => ['nullable', 'array', 'max:50'],
            'allocations.*.payment_schedule_id' => ['nullable', 'integer', 'distinct', Rule::exists('payment_schedules', 'id')],
            'allocations.*.invoice_id' => ['nullable', 'integer', 'distinct', 'min:1'],
            'allocations.*.amount' => ['nullable', 'decimal:0,4', 'gt:0', 'max:999999999999999.9999'],
        ];
    }

    public function paymentAttributes(): array
    {
        return $this->safe()->only([
            'idempotency_key', 'payment_type', 'amount', 'received_at', 'received_by_user_id',
            'invoice_id', 'channel', 'external_reference', 'notes',
        ]);
    }

    public function allocations(): array
    {
        return collect($this->validated('allocations', []))
            ->filter(fn (array $row) => filled($row['amount'] ?? null))
            ->map(fn (array $row) => [
                'payment_schedule_id' => $row['payment_schedule_id'] ?? null,
                'invoice_id' => $row['invoice_id'] ?? null,
                'amount' => $row['amount'],
            ])->values()->all();
    }
}
