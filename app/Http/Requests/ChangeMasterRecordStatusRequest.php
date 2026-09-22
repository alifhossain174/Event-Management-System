<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeMasterRecordStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('vendor') ?? $this->route('staff');

        return $model && $this->user()->can($this->input('action') === 'reactivate' ? 'reactivate' : 'archive', $model);
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['archive', 'reactivate'])], 'reason' => [Rule::requiredIf(fn () => $this->input('action') === 'archive'), 'nullable', 'string', 'max:2000']];
    }
}
