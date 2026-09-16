<?php

namespace App\Http\Requests\Settings;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('branches.configure');
    }

    public function rules(): array
    {
        $branch = $this->route('branch');
        $companyId = Company::query()->value('id') ?? 1;

        return [
            'code' => [
                'required', 'string', 'max:50', 'alpha_dash:ascii',
                Rule::unique('branches', 'code')->where('company_id', $companyId)->ignore($branch?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'timezone' => ['nullable', Rule::in(config('system-settings.timezones'))],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper((string) $this->string('code')),
            'country_code' => $this->filled('country_code') ? mb_strtoupper((string) $this->string('country_code')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
