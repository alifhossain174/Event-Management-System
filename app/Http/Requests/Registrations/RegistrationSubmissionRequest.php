<?php

namespace App\Http\Requests\Registrations;

use App\Models\RegistrationForm;
use App\Services\RegistrationValidationService;
use Illuminate\Foundation\Http\FormRequest;

final class RegistrationSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('public.registrations.store')) {
            return true;
        }

        $form = $this->route('registrationForm');

        return $form instanceof RegistrationForm && $this->user()?->can('submitOffline', $form);
    }

    public function rules(): array
    {
        $form = $this->route('registrationForm');
        $form->loadMissing('fields');

        return app(RegistrationValidationService::class)->rules($form);
    }
}
