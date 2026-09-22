<?php

namespace App\Http\Requests\Events;

use App\Services\EventModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

abstract class EventRequest extends FormRequest
{
    /** @return array<string, mixed> */
    protected function commonRules(): array
    {
        $event = $this->route('event');

        return [
            'name' => ['required', 'string', 'max:180'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where(fn ($query) => $query->where('status', 'active')->when($event?->client_id, fn ($query, $id) => $query->orWhere('id', $id)))],
            'event_category_id' => ['required', Rule::exists('event_categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true)->when($event?->event_category_id, fn ($query, $id) => $query->orWhere('id', $id)))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'manager_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at_local' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at_local'],
            'timezone' => ['required', Rule::in(config('system-settings.timezones', ['UTC']))],
            'primary_contact_name' => ['nullable', 'string', 'max:180'],
            'primary_contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'primary_contact_phone' => ['nullable', 'string', 'max:60', 'regex:/^[0-9+()\-\.\s]+$/'],
            'expected_guest_count' => ['nullable', 'integer', 'between:0,4294967295'],
            'theme' => ['nullable', 'string', 'max:180'],
            'dress_code' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'core_budget_estimate' => ['nullable', 'decimal:0,4', 'min:0', 'max:999999999999999.9999'],
        ];
    }

    /** @return array<string, mixed> */
    public function eventAttributes(): array
    {
        $data = $this->validated();
        $timezone = $data['timezone'];

        return [
            'client_id' => $data['client_id'] ?? null,
            'event_category_id' => $data['event_category_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'manager_user_id' => $data['manager_user_id'] ?? null,
            'name' => $data['name'],
            'starts_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at_local'], $timezone)->utc(),
            'ends_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at_local'], $timezone)->utc(),
            'timezone' => $timezone,
            'primary_contact_name' => $data['primary_contact_name'] ?? null,
            'primary_contact_email' => $data['primary_contact_email'] ?? null,
            'primary_contact_phone' => $data['primary_contact_phone'] ?? null,
            'expected_guest_count' => $data['expected_guest_count'] ?? null,
            'theme' => $data['theme'] ?? null,
            'dress_code' => $data['dress_code'] ?? null,
            'description' => $data['description'] ?? null,
            'core_budget_estimate' => $data['core_budget_estimate'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = collect($this->all())->map(function ($value) {
            if (! is_string($value)) {
                return $value;
            }

            $value = trim($value);

            return $value === '' ? null : $value;
        })->all();
        $this->replace($values);
    }

    protected function validateModuleKeys(Validator $validator): void
    {
        try {
            app(EventModuleService::class)->validateModuleKeys(array_values($this->input('enabled_modules', [])));
        } catch (ValidationException $exception) {
            $validator->errors()->add('enabled_modules', $exception->errors()['modules'][0]);
        }
    }
}
