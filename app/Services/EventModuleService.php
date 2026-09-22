<?php

namespace App\Services;

use App\Data\EventModulePlan;
use App\Models\EventTemplate;
use App\Models\ModuleDefinition;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EventModuleService
{
    /** @return list<string> */
    public function eventScopedKeys(): array
    {
        return array_values(config('event-modules.event_scoped_keys', []));
    }

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return config('event-modules.definitions', []);
    }

    /** @return Collection<int, ModuleDefinition> */
    public function eventScopedDefinitions(bool $activeOnly = false): Collection
    {
        return ModuleDefinition::query()
            ->where('is_event_scoped', true)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->get();
    }

    /** @param list<string> $keys
     * @return list<string>
     */
    public function validateModuleKeys(array $keys): array
    {
        $keys = array_values(array_unique($keys));
        $unknown = array_values(array_diff($keys, $this->eventScopedKeys()));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'modules' => 'Unknown or non-event module key(s): '.implode(', ', $unknown).'.',
            ]);
        }

        return $keys;
    }

    /** @param array<string, bool> $overrides */
    public function initializationPlan(?EventTemplate $template = null, array $overrides = []): EventModulePlan
    {
        $this->validateModuleKeys(array_keys($overrides));
        $enabledOverrides = array_keys(array_filter($overrides, fn (bool $enabled) => $enabled));
        if ($enabledOverrides !== []) {
            $available = ModuleDefinition::query()
                ->whereIn('key', $enabledOverrides)
                ->where('is_event_scoped', true)
                ->where('is_active', true)
                ->pluck('key')
                ->all();
            $unavailable = array_values(array_diff($enabledOverrides, $available));

            if ($unavailable !== []) {
                throw ValidationException::withMessages([
                    'modules' => 'Inactive module key(s) cannot be enabled for a new Event: '.implode(', ', $unavailable).'.',
                ]);
            }
        }

        $states = array_fill_keys($this->eventScopedKeys(), false);
        $tasks = [];
        $notes = null;
        $budgetLines = [];
        $templateId = null;

        if ($template) {
            if ($template->status !== 'active') {
                throw ValidationException::withMessages(['template' => 'Only an active template can initialize a new Event.']);
            }

            $template->loadMissing('modules.definition');
            foreach ($template->modules as $module) {
                $this->validateModuleKeys([$module->module_key]);
                if ($module->recommendation === 'default' && ($module->definition?->is_active ?? false)) {
                    $states[$module->module_key] = true;
                }
            }

            $templateId = $template->getKey();
            $tasks = array_values($template->starter_tasks ?? []);
            $notes = $template->service_notes;
            $budgetLines = array_values($template->budget_lines ?? []);
        }

        foreach ($overrides as $key => $enabled) {
            $states[$key] = (bool) $enabled;
        }

        $enabled = array_keys(array_filter($states));

        return new EventModulePlan(
            sourceTemplateId: $templateId,
            moduleStates: $states,
            starterTasks: $tasks,
            serviceNotes: $notes,
            budgetLines: $budgetLines,
            warnings: $this->dependencyWarnings($enabled),
        );
    }

    /**
     * @param  iterable<int|string, mixed>  $states
     * @return list<string>
     */
    public function enabledKeys(iterable $states): array
    {
        $enabled = [];
        foreach ($states as $key => $state) {
            if (is_string($key)) {
                if ((bool) $state) {
                    $enabled[] = $key;
                }

                continue;
            }

            $moduleKey = data_get($state, 'module_key');
            if ($moduleKey && (bool) data_get($state, 'is_enabled')) {
                $enabled[] = $moduleKey;
            }
        }

        return $this->validateModuleKeys($enabled);
    }

    /** @param list<string> $enabledKeys
     * @return list<string>
     */
    public function dependencyWarnings(array $enabledKeys): array
    {
        $enabledKeys = $this->validateModuleKeys($enabledKeys);
        $enabled = array_fill_keys($enabledKeys, true);
        $warnings = [];

        foreach (config('event-modules.dependencies', []) as $moduleKey => $rules) {
            if (! isset($enabled[$moduleKey])) {
                continue;
            }

            foreach ($rules as $rule) {
                if (collect($rule['keys'])->doesntContain(fn (string $key) => isset($enabled[$key]))) {
                    $warnings[] = $rule['message'];
                }
            }
        }

        return $warnings;
    }
}
