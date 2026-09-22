<?php

namespace App\Http\Requests\Bookings;

use App\Models\Booking;

final class StoreBookingRequest extends BookingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    public function rules(): array
    {
        return $this->commonRules();
    }
}
