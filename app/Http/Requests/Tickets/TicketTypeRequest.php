<?php

namespace App\Http\Requests\Tickets;

use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configure', [Ticket::class, $this->route('event')]) ?? false;
    }

    public function rules(): array
    {
        $event = $this->route('event');
        $type = $this->route('ticketType');

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('ticket_types')->where('event_id', $event?->getKey())->ignore($type?->getKey())],
            'category' => ['required', Rule::in(TicketType::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'quantity_total' => ['required', 'integer', 'min:1', 'max:1000000'],
            'price' => ['required', 'decimal:0,4', 'min:0'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'status' => ['required', Rule::in(['draft', 'on_sale', 'paused'])],
            'is_public' => ['required', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
