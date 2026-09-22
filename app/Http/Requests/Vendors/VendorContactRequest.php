<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Foundation\Http\FormRequest;

final class VendorContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('vendor'));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:180'], 'job_title' => ['nullable', 'string', 'max:120'], 'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'], 'phone' => ['nullable', 'required_without:email', 'string', 'max:60'], 'is_primary' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_primary' => $this->boolean('is_primary')]);
    }
}
