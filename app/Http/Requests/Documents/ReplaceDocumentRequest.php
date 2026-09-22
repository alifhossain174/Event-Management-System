<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class ReplaceDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return [
            'version_notes' => ['required', 'string', 'max:2000'],
            'file' => [
                'required',
                File::types(array_keys(config('documents.extensions', [])))
                    ->max((int) config('documents.max_kilobytes', 25600)),
            ],
        ];
    }
}
