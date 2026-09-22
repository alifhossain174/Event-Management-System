<?php

namespace App\Http\Requests\Settings;

use App\Support\MasterDataRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('master-data.configure');
    }

    public function rules(): array
    {
        $definition = app(MasterDataRegistry::class)->get($this->route('type'));
        $categoryId = $this->route('category');
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:160', 'alpha_dash:ascii', Rule::unique($definition['table'], 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
        ];

        if ($definition['direction'] ?? false) {
            $rules['direction'] = ['required', Rule::in(['income', 'expense', 'both'])];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? mb_strtolower((string) $this->string('slug')) : null,
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->integer('sort_order'),
        ]);
    }
}
