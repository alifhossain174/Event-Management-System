<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class LeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $this->user()->hasPermission('staff.manage-leave')
            || ($this->user()->hasPermission('staff.request-leave') && $staff->user_id === $this->user()->getKey());
    }

    public function rules(): array
    {
        return ['starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'leave_type' => ['required', 'string', 'max:80'], 'reason' => ['required', 'string', 'max:5000']];
    }
}
