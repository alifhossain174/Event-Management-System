<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class PerformanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('staff.manage-performance');
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer', 'exists:events,id'], 'period_starts_on' => ['nullable', 'date'],
            'period_ends_on' => ['nullable', 'date', 'after_or_equal:period_starts_on'],
            'score' => ['nullable', 'numeric', 'between:0,100'], 'summary' => ['required', 'string', 'max:10000'],
            'strengths' => ['nullable', 'string', 'max:10000'], 'improvement_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
