<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'document_category_id' => ['nullable', Rule::exists('document_categories', 'id')->whereNull('deleted_at')],
            'expiry_date' => ['nullable', 'date'],
            'version_notes' => ['nullable', 'string', 'max:2000'],
            'file' => [
                'required',
                File::types(array_keys(config('documents.extensions', [])))
                    ->max((int) config('documents.max_kilobytes', 25600)),
            ],
        ];
    }
}
