<?php

namespace App\Http\Requests\Staff;

use App\Models\StaffAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class StaffAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StaffAssignment::class);
    }

    public function rules(): array
    {
        return [
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'role_title' => ['required', 'string', 'max:180'], 'responsibilities' => ['nullable', 'string', 'max:10000'],
            'scheduled_starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'scheduled_ends_at_local' => ['required', 'date_format:Y-m-d\TH:i', 'after:scheduled_starts_at_local'],
            'responsible_manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'override_conflict' => ['sometimes', 'boolean'], 'override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function assignmentAttributes(): array
    {
        $data = $this->validated();
        $timezone = $this->route('event')->timezone;
        $data['scheduled_starts_at'] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['scheduled_starts_at_local'], $timezone)->utc();
        $data['scheduled_ends_at'] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['scheduled_ends_at_local'], $timezone)->utc();
        unset($data['scheduled_starts_at_local'], $data['scheduled_ends_at_local']);
        $data['override_conflict'] = (bool) ($data['override_conflict'] ?? false);

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['override_conflict' => $this->boolean('override_conflict')]);
    }
}
