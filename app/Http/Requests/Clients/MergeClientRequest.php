<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MergeClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('merge', $this->route('client')) ?? false;
    }

    public function rules(): array
    {
        return [
            'target_client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('status', 'active')),
                Rule::notIn([$this->route('client')?->getKey()]),
            ],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
