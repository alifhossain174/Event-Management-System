<?php

namespace App\Http\Requests\Communications;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

final class StoreReminderScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && ($this->user()?->hasPermission('communications.configure') ?? false)
            && ($this->user()?->can('viewModule', [$event, 'communications']) ?? false);
    }

    public function rules(): array
    {
        return [
            'due_at' => ['required', 'date', 'after:now'],
        ];
    }
}
