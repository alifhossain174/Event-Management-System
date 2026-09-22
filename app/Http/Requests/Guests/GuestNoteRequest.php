<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GuestNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addNote', $this->route('guest'));
    }

    public function rules(): array
    {
        return ['visibility' => ['required', Rule::in(['operations', 'private'])], 'body' => ['required', 'string', 'max:5000']];
    }
}
