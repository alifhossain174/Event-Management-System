<?php

namespace App\Http\Requests\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

final class StorePaymentScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('schedule', [Payment::class, $this->route('event')]);
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:191'],
            'amount_due' => ['required', 'decimal:0,4', 'gt:0', 'max:999999999999999.9999'],
            'due_date' => ['required', 'date'],
            'invoice_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
