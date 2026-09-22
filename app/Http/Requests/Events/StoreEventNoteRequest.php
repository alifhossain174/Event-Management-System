<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEventNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('addNote', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'is_pinned' => ['nullable', 'boolean'],
            'include_in_duplicate' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body')),
            'is_pinned' => $this->boolean('is_pinned'),
            'include_in_duplicate' => $this->boolean('include_in_duplicate'),
        ]);
    }
}
