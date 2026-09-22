<?php

namespace App\Services;

use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EventTemplateService
{
    public function __construct(
        private readonly EventModuleService $modules,
        private readonly AuditService $audit,
        private readonly StatusTransitionService $statuses,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): EventTemplate
    {
        return DB::transaction(function () use ($data, $actor) {
            $recommendations = $data['module_recommendations'] ?? [];
            unset($data['module_recommendations']);

            $template = EventTemplate::query()->create($data + [
                'status' => 'active',
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->syncModules($template, $recommendations);
            $this->audit->record('event-template.created', $template, [], $this->snapshot($template), $actor);

            return $template->fresh(['category', 'modules.definition']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(EventTemplate $template, array $data, User $actor): EventTemplate
    {
        return DB::transaction(function () use ($template, $data, $actor) {
            $locked = EventTemplate::query()->lockForUpdate()->findOrFail($template->getKey());
            $before = $this->snapshot($locked);
            $recommendations = $data['module_recommendations'] ?? [];
            unset($data['module_recommendations']);

            $locked->update($data + ['updated_by_user_id' => $actor->getKey()]);
            $this->syncModules($locked, $recommendations);
            $this->audit->record('event-template.updated', $locked, $before, $this->snapshot($locked), $actor);

            return $locked->fresh(['category', 'modules.definition']);
        });
    }

    public function duplicate(EventTemplate $source, User $actor): EventTemplate
    {
        return DB::transaction(function () use ($source, $actor) {
            $source->loadMissing('modules');
            $copy = EventTemplate::query()->create([
                'event_category_id' => $source->event_category_id,
                'source_template_id' => $source->getKey(),
                'name' => $source->name.' Copy',
                'slug' => $this->uniqueCopySlug($source->slug),
                'description' => $source->description,
                'service_notes' => $source->service_notes,
                'starter_tasks' => $source->starter_tasks,
                'budget_lines' => $source->budget_lines,
                'status' => 'active',
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            foreach ($source->modules as $module) {
                $copy->modules()->create($module->only(['module_key', 'recommendation', 'sort_order', 'notes']));
            }

            $this->audit->record('event-template.duplicated', $copy, [], [
                'source_template_id' => $source->getKey(),
                ...$this->snapshot($copy),
            ], $actor);

            return $copy;
        });
    }

    public function changeStatus(EventTemplate $template, string $action, User $actor, ?string $reason): void
    {
        $target = $action === 'archive' ? 'archived' : 'active';
        DB::transaction(function () use ($template, $target, $actor, $reason) {
            $template->update([
                'archived_at' => $target === 'archived' ? now() : null,
                'archived_by_user_id' => $target === 'archived' ? $actor->getKey() : null,
                'archive_reason' => $target === 'archived' ? $reason : null,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->statuses->transition($template, $target, $actor, $reason, auditAction: "event-template.{$target}");
        });
    }

    /** @param array<string, string> $recommendations */
    private function syncModules(EventTemplate $template, array $recommendations): void
    {
        $selected = collect($recommendations)->filter(fn (string $value) => in_array($value, ['default', 'optional'], true));
        $this->modules->validateModuleKeys($selected->keys()->all());
        $template->modules()->delete();

        $order = 0;
        foreach ($selected as $moduleKey => $recommendation) {
            $template->modules()->create([
                'module_key' => $moduleKey,
                'recommendation' => $recommendation,
                'sort_order' => $order++,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(EventTemplate $template): array
    {
        $template->loadMissing('modules');

        return [
            'name' => $template->name,
            'slug' => $template->slug,
            'event_category_id' => $template->event_category_id,
            'status' => $template->status,
            'module_recommendations' => $template->modules->mapWithKeys(fn ($module) => [$module->module_key => $module->recommendation])->all(),
            'starter_task_count' => count($template->starter_tasks ?? []),
            'budget_line_count' => count($template->budget_lines ?? []),
        ];
    }

    private function uniqueCopySlug(string $sourceSlug): string
    {
        $base = Str::limit($sourceSlug.'-copy', 175, '');
        $candidate = $base;
        $suffix = 2;

        while (EventTemplate::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
