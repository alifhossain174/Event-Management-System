<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

final class TaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attach', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
            'version_notes' => ['nullable', 'string', 'max:2000'],
            'document_category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'file' => ['required', 'file', 'max:'.config('documents.max_kilobytes', 25600)],
        ];
    }
}
