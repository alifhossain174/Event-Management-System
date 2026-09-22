<?php

namespace App\Http\Requests\Communications;

use App\Models\Event;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendOutboundMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $this->user()?->can('create', [OutboundMessage::class, $event]);
    }

    public function rules(): array
    {
        return [
            'message_template_id' => ['nullable', Rule::exists('message_templates', 'id')->where('is_active', true)->whereNull('archived_at')],
            'channel' => ['required', Rule::in(MessageTemplate::CHANNELS)],
            'category' => ['required', Rule::in(MessageTemplate::CATEGORIES)],
            'recipient_address' => ['required', 'string', 'max:320', Rule::when($this->input('channel') === 'email', ['email:rfc'])],
            'recipient_name' => ['nullable', 'string', 'max:191'],
            'subject' => ['nullable', 'string', 'max:191'],
            'body' => ['nullable', 'required_without:message_template_id', 'string', 'max:10000'],
            'marketing_consent_confirmed' => ['nullable', 'boolean', Rule::requiredIf($this->input('category') === 'marketing'), 'accepted_if:category,marketing'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
