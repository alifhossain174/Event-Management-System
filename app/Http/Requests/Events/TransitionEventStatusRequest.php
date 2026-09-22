<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionEventStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transition', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_values(array_diff(Event::STATUSES, ['draft'])))],
            'reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:2000'],
        ];
    }
}
