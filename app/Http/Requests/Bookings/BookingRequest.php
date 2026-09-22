<?php

namespace App\Http\Requests\Bookings;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BookingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    protected function commonRules(): array
    {
        return [
            'client_id' => ['required', Rule::exists('clients', 'id')->where('status', 'active')],
            'requested_event_category_id' => ['required', Rule::exists('event_categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'requested_starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'requested_ends_at_local' => ['required', 'date_format:Y-m-d\TH:i', 'after:requested_starts_at_local'],
            'timezone' => ['required', Rule::in(config('system-settings.timezones', ['UTC']))],
            'venue_preference' => ['nullable', 'string', 'max:255'],
            'expected_guest_count' => ['nullable', 'integer', 'between:0,4294967295'],
            'budget_estimate' => ['nullable', 'decimal:0,4', 'min:0', 'max:999999999999999.9999'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, mixed> */
    public function bookingAttributes(): array
    {
        $data = $this->validated();
        $timezone = $data['timezone'];

        return [
            'client_id' => $data['client_id'],
            'requested_event_category_id' => $data['requested_event_category_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'requested_starts_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['requested_starts_at_local'], $timezone)->utc(),
            'requested_ends_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['requested_ends_at_local'], $timezone)->utc(),
            'timezone' => $timezone,
            'venue_preference' => $data['venue_preference'] ?? null,
            'expected_guest_count' => $data['expected_guest_count'] ?? null,
            'budget_estimate' => $data['budget_estimate'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace(collect($this->all())->map(function ($value) {
            if (! is_string($value)) {
                return $value;
            }

            $value = trim($value);

            return $value === '' ? null : $value;
        })->all());
    }
}
