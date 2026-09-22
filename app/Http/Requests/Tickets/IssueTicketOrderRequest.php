<?php

namespace App\Http\Requests\Tickets;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IssueTicketOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('issue', [Ticket::class, $this->route('event')]) ?? false;
    }

    public function rules(): array
    {
        $eventId = $this->route('event')?->getKey();

        return [
            'ticket_type_id' => ['required', 'integer', Rule::exists('ticket_types', 'id')->where('event_id', $eventId)],
            'attendee_name' => ['required', 'string', 'max:191'], 'attendee_email' => ['nullable', 'email:rfc', 'max:320'],
            'attendee_phone' => ['nullable', 'string', 'max:50'], 'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'promo_code' => ['nullable', 'string', 'max:64'],
            'payment_id' => ['nullable', 'integer', Rule::exists('payments', 'id')->where('event_id', $eventId)],
            'registration_id' => ['nullable', 'integer', Rule::exists('registrations', 'id')->where('event_id', $eventId)],
            'source' => ['nullable', Rule::in(['manual', 'registration'])], 'notes' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
