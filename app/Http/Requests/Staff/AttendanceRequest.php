<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('staff.record-attendance');
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'staff_assignment_id' => ['nullable', 'integer', 'exists:staff_assignments,id'],
            'attendance_date' => ['required', 'date'], 'clocked_in_at' => ['nullable', 'date'],
            'clocked_out_at' => ['nullable', 'date', 'after:clocked_in_at'],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'leave'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
