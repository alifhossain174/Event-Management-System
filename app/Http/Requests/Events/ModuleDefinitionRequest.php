<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

final class ModuleDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('module_definition')) ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:150'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active'), 'sort_order' => $this->integer('sort_order')]);
    }
}
