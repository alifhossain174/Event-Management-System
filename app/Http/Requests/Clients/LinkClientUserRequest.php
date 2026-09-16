<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LinkClientUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignUser', $this->route('client')) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
                Rule::unique('clients', 'user_id')->ignore($this->route('client')),
            ],
        ];
    }
}
