<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

final class DuplicateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('duplicate', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'copy_notes' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'copy_notes' => $this->boolean('copy_notes'),
        ]);
    }
}
