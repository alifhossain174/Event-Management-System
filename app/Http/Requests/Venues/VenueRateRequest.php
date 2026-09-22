<?php

namespace App\Http\Requests\Venues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VenueRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('venue'));
    }

    public function rules(): array
    {
        return ['label' => ['required', 'string', 'max:180'], 'venue_space_id' => ['nullable', Rule::exists('venue_spaces', 'id')->where('venue_id', $this->route('venue')->getKey())->whereNull('deleted_at')],
            'rate_type' => ['nullable', 'string', 'max:80'], 'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.9999'], 'currency_code' => ['nullable', 'required_with:amount', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'effective_from' => ['nullable', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'], 'notes' => ['nullable', 'string', 'max:5000']];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency_code')) {
            $this->merge(['currency_code' => mb_strtoupper($this->string('currency_code')->toString())]);
        }
    }
}
