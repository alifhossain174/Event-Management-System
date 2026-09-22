<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GuestGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('guests.create') && $this->user()->can('viewModule', [$this->route('event'), 'guests']);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:191'], 'type' => ['required', Rule::in(['family', 'household', 'company', 'party', 'other'])], 'description' => ['nullable', 'string', 'max:2000'], 'is_vip' => ['nullable', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_vip' => $this->boolean('is_vip')]);
    }
}
