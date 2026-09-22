<?php

namespace App\Http\Requests\Tickets;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

final class ValidateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('validate', [Ticket::class, $this->route('event')]) ?? false;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'min:32', 'max:255'], 'method' => ['nullable', 'in:qr,manual']];
    }
}
