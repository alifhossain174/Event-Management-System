<?php

namespace App\Http\Requests\Registrations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('registration'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:2000', Rule::requiredIf($this->input('status') === 'rejected')],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
