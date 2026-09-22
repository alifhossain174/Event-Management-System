<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Foundation\Http\FormRequest;

final class VendorRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rate', $this->route('assignment'));
    }

    public function rules(): array
    {
        return ['score' => ['required', 'integer', 'between:1,5'], 'comments' => ['nullable', 'string', 'max:5000']];
    }
}
