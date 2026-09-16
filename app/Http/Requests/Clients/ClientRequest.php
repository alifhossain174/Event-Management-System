<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ClientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['individual', 'organization'])],
            'first_name' => [Rule::requiredIf(fn () => $this->input('type') === 'individual'), 'nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'organization_name' => [Rule::requiredIf(fn () => $this->input('type') === 'organization'), 'nullable', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_identifier' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'primary_email' => ['nullable', 'required_without:primary_phone', 'email:rfc', 'max:255'],
            'primary_phone' => ['nullable', 'required_without:primary_email', 'string', 'max:60', 'regex:/^[0-9+()\-\.\s]+$/'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state_region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'notes' => ['nullable', 'string', 'max:10000'],
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
}
