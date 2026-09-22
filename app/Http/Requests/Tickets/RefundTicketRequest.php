<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

final class RefundTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refund', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'], 'refunded_at' => ['required', 'date'],
            'channel' => ['nullable', 'string', 'max:100'], 'external_reference' => ['nullable', 'string', 'max:191'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
