<?php

namespace App\Http\Requests\Registrations;

use App\Models\RegistrationForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $form = $this->route('registrationForm');

        return $form instanceof RegistrationForm
            ? $this->user()->can('update', $form)
            : $this->user()->can('create', [RegistrationForm::class, $this->route('event')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'approval_required' => $this->boolean('approval_required'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'privacy_text' => ['nullable', 'string', 'max:5000'],
            'duplicate_policy' => ['required', Rule::in(RegistrationForm::DUPLICATE_POLICIES)],
            'approval_required' => ['required', 'boolean'],
            'confirmation_channel' => ['required', Rule::in(RegistrationForm::CONFIRMATION_CHANNELS)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
