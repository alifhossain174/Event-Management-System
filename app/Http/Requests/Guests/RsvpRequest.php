<?php

namespace App\Http\Requests\Guests;

use App\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRsvp', $this->route('guest'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(Guest::RSVP_STATUSES)], 'attending_count' => ['required', 'integer', 'min:0', 'max:100'], 'plus_one_count' => ['nullable', 'integer', 'min:0', 'max:20'], 'response_source' => ['required', Rule::in(['manager', 'guest', 'phone', 'email', 'import'])], 'response_note' => ['nullable', 'string', 'max:2000']];
    }
}
