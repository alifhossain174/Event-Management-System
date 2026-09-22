<?php

namespace App\Http\Requests\Bookings;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RescheduleBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reschedule', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [
            'requested_starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'requested_ends_at_local' => ['required', 'date_format:Y-m-d\TH:i', 'after:requested_starts_at_local'],
            'timezone' => ['required', Rule::in(config('system-settings.timezones', ['UTC']))],
            'venue_preference' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:2000'],
            'override_conflicts' => ['nullable', 'boolean'],
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->validated('requested_starts_at_local'), $this->validated('timezone'))->utc();
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->validated('requested_ends_at_local'), $this->validated('timezone'))->utc();
    }

    protected function prepareForValidation(): void
    {
        foreach (['venue_preference', 'reason'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key)) ?: null]);
            }
        }
    }
}
