<?php

namespace App\Http\Requests\Venues;

use Illuminate\Foundation\Http\FormRequest;

final class VenueSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('venue'));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:180'], 'code' => ['nullable', 'string', 'max:80'], 'capacity' => ['nullable', 'integer', 'min:1', 'max:10000000'], 'is_exclusive' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:5000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_exclusive' => $this->boolean('is_exclusive')]);
    }
}
