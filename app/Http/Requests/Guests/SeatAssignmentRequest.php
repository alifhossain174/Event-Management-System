<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SeatAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSeating', $this->route('guest'));
    }

    public function rules(): array
    {
        $eventId = $this->route('event')->getKey();
        $guestId = $this->route('guest')->getKey();

        return [
            'guest_group_id' => ['nullable', Rule::exists('guest_groups', 'id')->where('event_id', $eventId)->whereNull('archived_at')],
            'table_label' => ['required', 'string', 'max:100'],
            'seat_label' => ['required', 'string', 'max:100', Rule::unique('seat_assignments')->where(fn ($query) => $query->where('event_id', $eventId)->where('table_label', $this->input('table_label')))->ignore($guestId, 'guest_id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
