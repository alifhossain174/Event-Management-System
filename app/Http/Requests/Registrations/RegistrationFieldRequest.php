<?php

namespace App\Http\Requests\Registrations;

use App\Models\RegistrationField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrationFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', $this->route('registrationForm'));
    }

    protected function prepareForValidation(): void
    {
        $options = $this->input('options');
        if (is_string($options)) {
            $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $options) ?: [])));
        }
        $this->merge([
            'options' => $options,
            'is_required' => $this->boolean('is_required'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $form = $this->route('registrationForm');
        $field = $this->route('registrationField');

        return [
            'key' => ['required', 'alpha_dash:ascii', 'max:64', Rule::unique('registration_fields', 'key')->where('registration_form_id', $form->getKey())->ignore($field)],
            'type' => ['required', Rule::in(RegistrationField::TYPES)],
            'label' => ['required', 'string', 'max:191'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'options' => ['nullable', 'array', 'max:100', Rule::requiredIf(fn () => in_array($this->input('type'), ['select', 'radio', 'checkbox'], true))],
            'options.*' => ['required', 'string', 'max:191', 'distinct'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'min_length' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:10000', 'gte:min_length'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric', 'gte:min_value'],
            'earliest_date' => ['nullable', 'date_format:Y-m-d'],
            'latest_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:earliest_date'],
            'max_selections' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
