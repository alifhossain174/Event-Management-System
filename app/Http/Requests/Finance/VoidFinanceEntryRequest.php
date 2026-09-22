<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class VoidFinanceEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('income') ?? $this->route('expense'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
