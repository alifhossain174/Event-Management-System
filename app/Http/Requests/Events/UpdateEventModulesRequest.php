<?php

namespace App\Http\Requests\Events;

use App\Services\EventModuleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final class UpdateEventModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageModules', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled_modules' => ['nullable', 'array'],
            'enabled_modules.*' => ['string', 'distinct', Rule::in(config('event-modules.event_scoped_keys', []))],
            'confirm_disable' => ['nullable', 'boolean'],
            'confirm_disable_with_data' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            try {
                app(EventModuleService::class)->validateModuleKeys(array_values($this->input('enabled_modules', [])));
            } catch (ValidationException $exception) {
                $validator->errors()->add('enabled_modules', $exception->errors()['modules'][0]);
            }
        }];
    }

    /** @return list<string> */
    public function enabledModules(): array
    {
        return array_values($this->validated('enabled_modules', []));
    }

    public function reason(): ?string
    {
        $reason = trim((string) $this->validated('reason', ''));

        return $reason !== '' ? $reason : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirm_disable' => $this->boolean('confirm_disable'),
            'confirm_disable_with_data' => $this->boolean('confirm_disable_with_data'),
        ]);
    }
}
