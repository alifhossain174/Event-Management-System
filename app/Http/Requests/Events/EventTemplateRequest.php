<?php

namespace App\Http\Requests\Events;

use App\Models\EventTemplate;
use App\Services\EventModuleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final class EventTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('event_template');

        return $template
            ? $this->user()?->can('update', $template) ?? false
            : $this->user()?->can('create', EventTemplate::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:190', 'alpha_dash:ascii', Rule::unique('event_templates', 'slug')->ignore($this->route('event_template'))],
            'event_category_id' => ['nullable', Rule::exists('event_categories', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'service_notes' => ['nullable', 'string', 'max:10000'],
            'module_recommendations' => ['nullable', 'array'],
            'module_recommendations.*' => [Rule::in(['disabled', 'default', 'optional'])],
            'starter_tasks_text' => ['nullable', 'string', 'max:10000'],
            'budget_lines_text' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            try {
                app(EventModuleService::class)->validateModuleKeys(array_keys($this->input('module_recommendations', [])));
            } catch (ValidationException $exception) {
                $validator->errors()->add('module_recommendations', $exception->validator->errors()->first('modules'));
            }

            foreach ($this->budgetLines() as $index => $line) {
                if (! in_array($line['direction'], ['income', 'expense'], true)) {
                    $validator->errors()->add('budget_lines_text', 'Budget line '.($index + 1).' must start with income or expense.');
                }
                if ($line['label'] === '') {
                    $validator->errors()->add('budget_lines_text', 'Budget line '.($index + 1).' requires a label.');
                }
                if ($line['amount'] !== null && (! is_numeric($line['amount']) || (float) $line['amount'] < 0)) {
                    $validator->errors()->add('budget_lines_text', 'Budget line '.($index + 1).' amount must be zero or greater.');
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function templateData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'event_category_id' => $validated['event_category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'service_notes' => $validated['service_notes'] ?? null,
            'starter_tasks' => collect($this->lines((string) ($validated['starter_tasks_text'] ?? '')))
                ->map(fn (string $title) => ['title' => $title])->values()->all(),
            'budget_lines' => $this->budgetLines(),
            'module_recommendations' => $validated['module_recommendations'] ?? [],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $this->merge([
            'name' => $name,
            'slug' => Str::slug($this->input('slug') ?: $name),
        ]);
    }

    /** @return list<string> */
    private function lines(string $value): array
    {
        return collect(preg_split('/\R/', $value) ?: [])->map(fn (string $line) => trim($line))->filter()->values()->all();
    }

    /** @return list<array{direction: string, label: string, amount: string|null}> */
    private function budgetLines(): array
    {
        return collect($this->lines((string) $this->input('budget_lines_text')))->map(function (string $line) {
            $parts = array_pad(array_map('trim', explode('|', $line, 3)), 3, null);

            return ['direction' => mb_strtolower((string) $parts[0]), 'label' => (string) $parts[1], 'amount' => filled($parts[2]) ? (string) $parts[2] : null];
        })->values()->all();
    }
}
