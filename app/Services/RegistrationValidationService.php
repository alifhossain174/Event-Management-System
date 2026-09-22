<?php

namespace App\Services;

use App\Models\RegistrationField;
use App\Models\RegistrationForm;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class RegistrationValidationService
{
    /** @return array<string, list<mixed>> */
    public function rules(RegistrationForm $form): array
    {
        $emailRequired = $form->duplicate_policy === 'block_email' || $form->confirmation_channel === 'email';
        $rules = [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'registrant_name' => ['required', 'string', 'max:191'],
            'registrant_email' => [$emailRequired ? 'required' : 'nullable', 'email:rfc', 'max:320'],
            'registrant_phone' => [$form->confirmation_channel === 'sms' ? 'required' : 'nullable', 'string', 'max:50'],
            'website' => ['nullable', 'max:0'],
            'responses' => ['array'],
        ];

        foreach ($form->fields as $field) {
            $rules['responses.'.$field->key] = $this->fieldRules($field);
            if ($field->type === 'checkbox' && $field->options) {
                $rules['responses.'.$field->key.'.*'] = [Rule::in($field->options)];
            }
        }

        return $rules;
    }

    /** @return list<mixed> */
    private function fieldRules(RegistrationField $field): array
    {
        $constraints = $field->validation_constraints ?? [];
        $rules = [$field->is_required ? 'required' : 'nullable'];

        if ($field->type === 'checkbox') {
            $rules[] = 'array';
            if (isset($constraints['max_selections'])) {
                $rules[] = 'max:'.(int) $constraints['max_selections'];
            }

            return $rules;
        }

        if ($field->type === 'consent') {
            $rules[] = $field->is_required ? 'accepted' : 'boolean';

            return $rules;
        }

        $rules[] = match ($field->type) {
            'email' => 'email:rfc',
            'number' => 'numeric',
            'date' => 'date_format:Y-m-d',
            default => 'string',
        };

        if (in_array($field->type, ['select', 'radio'], true)) {
            $rules[] = Rule::in($field->options ?? []);
        }
        if (isset($constraints['min_length']) && ! in_array($field->type, ['number', 'date'], true)) {
            $rules[] = 'min:'.(int) $constraints['min_length'];
        }
        if (isset($constraints['max_length']) && ! in_array($field->type, ['number', 'date'], true)) {
            $rules[] = 'max:'.(int) $constraints['max_length'];
        }
        if (isset($constraints['min_value']) && $field->type === 'number') {
            $rules[] = 'min:'.(float) $constraints['min_value'];
        }
        if (isset($constraints['max_value']) && $field->type === 'number') {
            $rules[] = 'max:'.(float) $constraints['max_value'];
        }
        if (isset($constraints['earliest_date']) && $field->type === 'date') {
            $rules[] = 'after_or_equal:'.$constraints['earliest_date'];
        }
        if (isset($constraints['latest_date']) && $field->type === 'date') {
            $rules[] = 'before_or_equal:'.$constraints['latest_date'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizedConstraints(string $type, array $data): array
    {
        $allowed = match ($type) {
            'number' => ['min_value', 'max_value'],
            'date' => ['earliest_date', 'latest_date'],
            'checkbox' => ['max_selections'],
            default => ['min_length', 'max_length'],
        };

        return array_filter(Arr::only($data, $allowed), fn ($value) => filled($value));
    }
}
