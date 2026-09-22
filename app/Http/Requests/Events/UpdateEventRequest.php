<?php

namespace App\Http\Requests\Events;

final class UpdateEventRequest extends EventRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return $this->commonRules();
    }
}
