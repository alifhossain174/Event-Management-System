<?php

namespace App\Http\Requests\Registrations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConvertRegistrationGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('convertGuest', $this->route('registration'));
    }

    public function rules(): array
    {
        $registration = $this->route('registration');

        return [
            'existing_guest_id' => ['nullable', 'integer', Rule::exists('guests', 'id')->where(fn ($query) => $query->where('event_id', $registration->event_id)->whereNull('archived_at'))],
        ];
    }
}
