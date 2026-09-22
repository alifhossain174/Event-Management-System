<?php

namespace App\Http\Requests\Venues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EventVenueAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewModule', [$this->route('event'), 'venue']) && $this->user()->hasPermission('venues.allocate');
    }

    public function rules(): array
    {
        return ['venue_id' => ['required', Rule::exists('venues', 'id')->where('status', 'active')], 'venue_space_id' => ['nullable', 'integer', 'exists:venue_spaces,id'],
            'status' => ['required', Rule::in(['planned', 'confirmed'])], 'is_exclusive' => ['nullable', 'boolean'],
            'quoted_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.9999'], 'currency_code' => ['nullable', 'required_with:quoted_price', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'rate_type_snapshot' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string', 'max:5000'],
            'override_conflict' => ['nullable', 'boolean'], 'override_reason' => ['nullable', 'required_if:override_conflict,1', 'string', 'min:5', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_exclusive' => $this->boolean('is_exclusive'), 'override_conflict' => $this->boolean('override_conflict')]);
        if ($this->filled('currency_code')) {
            $this->merge(['currency_code' => mb_strtoupper($this->string('currency_code')->toString())]);
        }
    }
}
