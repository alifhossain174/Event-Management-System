<?php

namespace App\Http\Requests\Venues;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue ? $this->user()->can('update', $venue) : $this->user()->can('create', Venue::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'], 'type' => ['required', Rule::in(['owned', 'third_party'])],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'parking_capacity' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'address_line_1' => ['nullable', 'string', 'max:255'], 'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'], 'state_region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'], 'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace(collect($this->all())->map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value)->all());
    }
}
