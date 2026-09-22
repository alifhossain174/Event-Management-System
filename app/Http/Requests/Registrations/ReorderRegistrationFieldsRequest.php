<?php

namespace App\Http\Requests\Registrations;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderRegistrationFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', $this->route('registrationForm'));
    }

    public function rules(): array
    {
        return [
            'field_ids' => ['required', 'array'], 'field_ids.*' => ['required', 'integer', 'distinct'],
            'positions' => ['required', 'array'], 'positions.*' => ['required', 'integer', 'min:0', 'max:65000'],
        ];
    }
}
