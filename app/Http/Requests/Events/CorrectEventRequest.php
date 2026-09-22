<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

final class CorrectEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('correct', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
