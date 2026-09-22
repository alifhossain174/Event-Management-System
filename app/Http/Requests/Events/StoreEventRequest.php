<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreEventRequest extends EventRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    public function rules(): array
    {
        return $this->commonRules() + [
            'event_template_id' => ['nullable', Rule::exists('event_templates', 'id')->where('status', 'active')],
            'enabled_modules' => ['nullable', 'array'],
            'enabled_modules.*' => ['string', 'distinct', Rule::in(config('event-modules.event_scoped_keys', []))],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateModuleKeys($validator)];
    }

    /** @return array<string, mixed> */
    public function eventAttributes(): array
    {
        return parent::eventAttributes() + ['event_template_id' => $this->validated('event_template_id')];
    }

    /** @return list<string> */
    public function enabledModules(): array
    {
        return array_values($this->validated('enabled_modules', []));
    }
}
