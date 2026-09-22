<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Foundation\Http\FormRequest;

final class VendorServiceAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('vendor'));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:180'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
