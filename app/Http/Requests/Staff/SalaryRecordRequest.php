<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('staff.manage-salary');
    }

    public function rules(): array
    {
        return [
            'period_starts_on' => ['required', 'date'], 'period_ends_on' => ['required', 'date', 'after_or_equal:period_starts_on'],
            'amount' => ['required', 'decimal:0,4', 'min:0', 'max:999999999999999.9999'],
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'payment_status' => ['required', Rule::in(['due', 'paid'])], 'paid_on' => ['nullable', 'date', 'required_if:payment_status,paid'],
            'payment_reference' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency_code')) {
            $this->merge(['currency_code' => mb_strtoupper((string) $this->input('currency_code'))]);
        }
    }
}
