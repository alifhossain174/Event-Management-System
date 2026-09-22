<?php

namespace App\Http\Requests\Guests;

use App\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guest = $this->route('guest');

        return $guest instanceof Guest
            ? $this->user()->can('update', $guest)
            : $this->user()->can('create', [Guest::class, $this->route('event')]);
    }

    public function rules(): array
    {
        return [
            'external_reference' => ['nullable', 'string', 'max:100', Rule::unique('guests')->where('event_id', $this->route('event')->getKey())->ignore($this->route('guest')?->getKey())],
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:320'], 'phone' => ['nullable', 'string', 'max:50'],
            'is_vip' => ['nullable', 'boolean'], 'plus_one_policy' => ['required', Rule::in(['none', 'allowed', 'approval_required'])],
            'plus_one_limit' => ['required_unless:plus_one_policy,none', 'integer', 'min:0', 'max:20'],
            'invited_party_size' => ['required', 'integer', 'min:1', 'max:100'],
            'guest_group_id' => ['nullable', 'integer', 'exists:guest_groups,id'],
            'relationship_label' => ['nullable', 'string', 'max:80'], 'source' => ['nullable', Rule::in(['manual', 'import', 'registration'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_vip' => $this->boolean('is_vip'), 'plus_one_limit' => $this->input('plus_one_policy') === 'none' ? 0 : $this->input('plus_one_limit')]);
    }
}
