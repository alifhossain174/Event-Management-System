<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Foundation\Http\FormRequest;

final class VendorWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('vendors.assign')
            && $this->user()->can('view', $this->route('assignment'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['required', 'string', 'max:10000'],
            'deliverables' => ['nullable', 'string', 'max:10000'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
