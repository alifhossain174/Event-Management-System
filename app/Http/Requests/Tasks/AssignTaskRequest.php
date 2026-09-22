<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

final class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:staff_profile_id', 'prohibits:staff_profile_id'],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id', 'required_without:user_id', 'prohibits:user_id'],
        ];
    }
}
