<?php

namespace App\Http\Requests\Venues;

use Illuminate\Foundation\Http\FormRequest;

final class CancelVenueAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewModule', [$this->route('event'), 'venue']) && $this->user()->hasPermission('venues.allocate');
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:2000']];
    }
}
