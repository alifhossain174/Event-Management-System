<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeClientStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->input('action') === 'reactivate' ? 'reactivate' : 'archive';

        return $this->user()?->can($ability, $this->route('client')) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['archive', 'reactivate'])],
            'reason' => ['nullable', 'required_if:action,archive', 'string', 'max:2000'],
        ];
    }
}
