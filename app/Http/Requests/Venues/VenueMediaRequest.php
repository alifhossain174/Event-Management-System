<?php

namespace App\Http\Requests\Venues;

use Illuminate\Foundation\Http\FormRequest;

final class VenueMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('venue')) && $this->user()->hasPermission('documents.create');
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:25600'], 'caption' => ['nullable', 'string', 'max:255'], 'alt_text' => ['required', 'string', 'max:255'], 'is_primary' => ['nullable', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_primary' => $this->boolean('is_primary')]);
    }
}
