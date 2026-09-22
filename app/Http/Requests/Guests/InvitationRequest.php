<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invite', $this->route('guest'));
    }

    public function rules(): array
    {
        return ['delivery_channel' => ['required', Rule::in(['manual', 'email', 'sms', 'whatsapp', 'print'])], 'expires_at' => ['nullable', 'date', 'after:now'], 'mark_sent' => ['nullable', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['mark_sent' => $this->boolean('mark_sent')]);
    }
}
