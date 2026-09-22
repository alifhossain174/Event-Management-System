<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VendorAssignmentTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('assignment'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'in_progress', 'completed', 'cancelled'])],
            'reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:2000'],
            'completion_notes' => ['nullable', 'required_if:status,completed', 'string', 'max:10000'],
        ];
    }
}
