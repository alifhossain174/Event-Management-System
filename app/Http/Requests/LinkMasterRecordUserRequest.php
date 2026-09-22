<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LinkMasterRecordUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('vendor') ?? $this->route('staff');

        return $model && $this->user()->can('assignUser', $model);
    }

    public function rules(): array
    {
        return ['user_id' => ['nullable', 'integer', 'exists:users,id']];
    }
}
