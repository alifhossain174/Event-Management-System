<?php

namespace App\Http\Requests\Bookings;

use App\Models\Booking;
use App\Services\EventModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final class ConvertBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('convert', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'event_category_id' => ['required', Rule::exists('event_categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'event_template_id' => ['nullable', Rule::exists('event_templates', 'id')->where('status', 'active')],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'manager_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at_local' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at_local'],
            'timezone' => ['required', Rule::in(config('system-settings.timezones', ['UTC']))],
            'expected_guest_count' => ['nullable', 'integer', 'between:0,4294967295'],
            'core_budget_estimate' => ['nullable', 'decimal:0,4', 'min:0', 'max:999999999999999.9999'],
            'description' => ['nullable', 'string', 'max:10000'],
            'enabled_modules' => ['nullable', 'array'],
            'enabled_modules.*' => ['string', 'distinct', Rule::in(config('event-modules.event_scoped_keys', []))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            try {
                app(EventModuleService::class)->validateModuleKeys(array_values((array) $this->input('enabled_modules', [])));
            } catch (ValidationException $exception) {
                $validator->errors()->add('enabled_modules', $exception->errors()['modules'][0]);
            }
        }];
    }

    /** @return array<string, mixed> */
    public function eventAttributes(): array
    {
        /** @var Booking $booking */
        $booking = $this->route('booking');
        $data = $this->validated();
        $timezone = $data['timezone'];

        return [
            'event_category_id' => $data['event_category_id'],
            'event_template_id' => $data['event_template_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'manager_user_id' => $data['manager_user_id'] ?? null,
            'name' => $data['name'],
            'starts_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at_local'], $timezone)->utc(),
            'ends_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at_local'], $timezone)->utc(),
            'timezone' => $timezone,
            'primary_contact_name' => $booking->client->display_name,
            'primary_contact_email' => $booking->client->primary_email,
            'primary_contact_phone' => $booking->client->primary_phone,
            'expected_guest_count' => $data['expected_guest_count'] ?? null,
            'core_budget_estimate' => $data['core_budget_estimate'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }

    /** @return list<string> */
    public function enabledModules(): array
    {
        return array_values((array) $this->validated('enabled_modules', []));
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
