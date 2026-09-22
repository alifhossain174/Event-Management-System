<?php

namespace App\Http\Requests\Vendors;

use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VendorAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $this->user()->hasPermission('vendors.manage-availability')
            && $this->user()->can('view', $vendor);
    }

    public function rules(): array
    {
        return [
            'starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at_local' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at_local'],
            'status' => ['required', Rule::in(['available', 'unavailable'])],
            'conflict_action' => ['required', Rule::in(['warn', 'block'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function availabilityAttributes(): array
    {
        $data = $this->validated();
        $timezone = app(SettingsService::class)->string('general.timezone');

        return [
            'starts_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at_local'], $timezone)->utc(),
            'ends_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at_local'], $timezone)->utc(),
            'status' => $data['status'],
            'conflict_action' => $data['conflict_action'],
            'notes' => $data['notes'] ?? null,
        ];
    }
}
