<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GuestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('archive', $this->route('guest'));
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['archive', 'reactivate'])], 'reason' => ['nullable', 'required_if:action,archive', 'string', 'min:5', 'max:2000']];
    }
}
