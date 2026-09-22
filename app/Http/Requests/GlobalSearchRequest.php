<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('dashboard.view') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100']];
    }
}
