<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class ArchiveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }
}
