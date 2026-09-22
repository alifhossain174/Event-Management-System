<?php

namespace App\Http\Requests\Finance;

use App\Models\Expense;
use App\Models\Income;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FinanceEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('income') ?? $this->route('expense');
        if ($entry) {
            return $this->user()->can('update', $entry);
        }

        $model = $this->routeIs('events.budget.incomes.store') ? Income::class : Expense::class;

        return $this->user()->can('create', [$model, $this->route('event')]);
    }

    public function rules(): array
    {
        $income = $this->routeIs('events.budget.incomes.*');

        return [
            'finance_category_id' => ['required', Rule::exists('finance_categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'client_id' => [$income ? 'nullable' : 'prohibited', Rule::exists('clients', 'id')],
            'vendor_id' => [$income ? 'prohibited' : 'nullable', Rule::exists('vendors', 'id')],
            'amount' => ['required', 'decimal:0,4', 'gt:0', 'max:999999999999999.9999'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'evidence_document_id' => ['nullable', Rule::exists('documents', 'id')->whereNull('deleted_at')],
        ];
    }

    public function financeAttributes(): array
    {
        return $this->safe()->only([
            'finance_category_id', 'client_id', 'vendor_id', 'amount',
            'transaction_date', 'description', 'evidence_document_id',
        ]);
    }
}
