<?php

namespace App\Http\Requests\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

final class CancelPaymentScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schedule = $this->route('schedule');

        return $schedule && $schedule->event_id === $this->route('event')->getKey()
            && $this->user()->can('schedule', [Payment::class, $this->route('event')]);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
