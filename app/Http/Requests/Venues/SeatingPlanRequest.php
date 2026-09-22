<?php

namespace App\Http\Requests\Venues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SeatingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('venue'));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:180'], 'venue_space_id' => ['nullable', Rule::exists('venue_spaces', 'id')->where('venue_id', $this->route('venue')->getKey())->whereNull('deleted_at')], 'capacity' => ['nullable', 'integer', 'min:1', 'max:10000000'], 'layout_notes' => ['nullable', 'string', 'max:10000']];
    }
}
