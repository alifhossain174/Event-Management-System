<?php

namespace App\Http\Requests\Staff;

use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('staff.schedule');
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer', 'exists:events,id'], 'title' => ['required', 'string', 'max:180'],
            'starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at_local' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at_local'],
            'location' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'],
            'override_conflict' => ['sometimes', 'boolean'], 'override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function shiftAttributes(): array
    {
        $data = $this->validated();
        $timezone = app(SettingsService::class)->string('general.timezone');
        $data['starts_at'] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at_local'], $timezone)->utc();
        $data['ends_at'] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at_local'], $timezone)->utc();
        unset($data['starts_at_local'], $data['ends_at_local']);
        $data['override_conflict'] = (bool) ($data['override_conflict'] ?? false);

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['override_conflict' => $this->boolean('override_conflict')]);
    }
}
