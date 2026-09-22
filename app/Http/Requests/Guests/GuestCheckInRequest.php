<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;

final class GuestCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('guests.check-in') && $this->user()->can('viewModule', [$this->route('event'), 'guests']);
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'size:43', 'regex:/^[A-Za-z0-9_-]+$/'], 'party_size' => ['nullable', 'integer', 'min:1', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000']];
    }
}
