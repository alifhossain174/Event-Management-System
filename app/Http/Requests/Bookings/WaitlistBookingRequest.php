<?php

namespace App\Http\Requests\Bookings;

use Illuminate\Foundation\Http\FormRequest;

final class WaitlistBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('waitlist', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }
}
