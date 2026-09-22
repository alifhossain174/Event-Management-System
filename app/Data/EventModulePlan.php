<?php

namespace App\Data;

final readonly class EventModulePlan
{
    /**
     * @param  array<string, bool>  $moduleStates
     * @param  list<array<string, mixed>>  $starterTasks
     * @param  list<array<string, mixed>>  $budgetLines
     * @param  list<string>  $warnings
     */
    public function __construct(
        public ?int $sourceTemplateId,
        public array $moduleStates,
        public array $starterTasks,
        public ?string $serviceNotes,
        public array $budgetLines,
        public array $warnings,
    ) {}

    /** @return list<string> */
    public function enabledKeys(): array
    {
        return array_keys(array_filter($this->moduleStates));
    }
}
