<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event')->budget);
    }

    public function rules(): array
    {
        return [
            'finance_category_id' => ['required', Rule::exists('finance_categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'description' => ['required', 'string', 'max:255'],
            'planned_amount' => ['required', 'decimal:0,4', 'min:0', 'max:999999999999999.9999'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
