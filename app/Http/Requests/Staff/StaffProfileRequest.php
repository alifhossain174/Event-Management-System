<?php

namespace App\Http\Requests\Staff;

use App\Models\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('staff');

        return $profile ? $this->user()->can('update', $profile) : $this->user()->can('create', StaffProfile::class);
    }

    public function rules(): array
    {
        return ['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'], 'phone' => ['nullable', 'required_without:email', 'string', 'max:60', 'regex:/^[0-9+()\-.\s]+$/'], 'role_title' => ['nullable', 'string', 'max:150'], 'employment_status' => ['required', Rule::in(['active', 'probation', 'on_leave', 'separated'])], 'default_availability_notes' => ['nullable', 'string', 'max:5000'], 'address' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:10000'], 'branch_id' => ['nullable', Rule::exists('branches', 'id')->whereNull('deleted_at')], 'department_id' => ['nullable', Rule::exists('departments', 'id')->whereNull('deleted_at')->where('is_active', true)]];
    }

    protected function prepareForValidation(): void
    {
        $this->replace(collect($this->all())->map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value)->all());
    }
}
