<?php

namespace App\Http\Requests\Communications;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('message_template');

        return $template
            ? $this->user()?->can('update', $template) ?? false
            : $this->user()?->can('create', MessageTemplate::class) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('message_template')?->getKey();

        return [
            'key' => ['nullable', 'string', 'max:100', 'alpha_dash:ascii', Rule::unique('message_templates', 'key')->ignore($id)],
            'name' => ['required', 'string', 'max:191'],
            'channel' => ['required', Rule::in(MessageTemplate::CHANNELS)],
            'category' => ['required', Rule::in(MessageTemplate::CATEGORIES)],
            'subject' => ['nullable', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
