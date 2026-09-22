<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;

final class RevokeInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invite', $this->route('guest'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:2000']];
    }
}
