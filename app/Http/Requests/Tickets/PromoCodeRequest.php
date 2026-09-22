<?php

namespace App\Http\Requests\Tickets;

use App\Models\PromoCode;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configure', [Ticket::class, $this->route('event')]) ?? false;
    }

    public function rules(): array
    {
        $event = $this->route('event');
        $promo = $this->route('promoCode');

        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('promo_codes')->where('event_id', $event?->getKey())->ignore($promo?->getKey())],
            'name' => ['required', 'string', 'max:191'], 'description' => ['nullable', 'string', 'max:5000'],
            'discount_type' => ['required', Rule::in(PromoCode::TYPES)],
            'discount_value' => ['required', 'decimal:0,4', 'gt:0'],
            'valid_from' => ['nullable', 'date'], 'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('discount_type') === 'percentage' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100.');
            }
        });
    }
}
