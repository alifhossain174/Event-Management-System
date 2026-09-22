<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventStatusHistory;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EventService
{
    public function __construct(
        private readonly EventReferenceService $references,
        private readonly EventModuleService $modulePlans,
        private readonly EventModuleSettingService $moduleSettings,
        private readonly EventTimelineService $timeline,
        private readonly AuditService $audit,
        private readonly SettingsService $settings,
        private readonly BranchScope $branches,
    ) {}

    /** @param array<string, mixed> $attributes
     * @param  list<string>  $enabledModules
     */
    public function create(array $attributes, array $enabledModules, User $actor): Event
    {
        $this->ensureBranchAccess($attributes, $actor);
        $template = isset($attributes['event_template_id'])
            ? EventTemplate::query()->where('status', 'active')->findOrFail($attributes['event_template_id'])
            : null;
        $overrides = array_fill_keys($this->modulePlans->eventScopedKeys(), false);
        foreach ($enabledModules as $key) {
            $overrides[$key] = true;
        }
        $plan = $this->modulePlans->initializationPlan($template, $overrides);

        return DB::transaction(function () use ($attributes, $template, $plan, $actor) {
            $event = Event::query()->create($attributes + [
                'reference_number' => $this->references->next(),
                'booking_id' => null,
                'status' => 'draft',
                'currency_code' => $this->settings->string('general.currency'),
                'template_snapshot' => $template ? [
                    'template_id' => $template->getKey(),
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'service_notes' => $plan->serviceNotes,
                    'starter_tasks' => $plan->starterTasks,
                    'budget_lines' => $plan->budgetLines,
                    'module_states' => $plan->moduleStates,
                    'applied_at' => now()->toIso8601String(),
                ] : null,
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            $this->moduleSettings->initialize($event, $plan, $actor, $template ? 'template' : 'manual');
            EventStatusHistory::query()->create([
                'event_id' => $event->getKey(),
                'from_status' => null,
                'to_status' => 'draft',
                'actor_type' => 'user',
                'actor_user_id' => $actor->getKey(),
                'reason' => 'Event created',
                'changed_at' => now(),
            ]);
            $this->timeline->record($event, 'created', 'Event created', $actor);
            $this->audit->record('event.created', $event, [], $this->snapshot($event), $actor);

            return $event->fresh(['moduleSettings.definition', 'client', 'category']);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Event $event, array $attributes, User $actor): Event
    {
        if ($event->isOperationallyReadOnly()) {
            throw ValidationException::withMessages(['event' => 'Completed, cancelled, or archived Events are read-only. Use the privileged correction action for a completed Event.']);
        }

        $this->ensureBranchAccess($attributes, $actor);

        DB::transaction(function () use ($event, $attributes, $actor) {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $before = $this->snapshot($locked);
            $locked->update($attributes + ['updated_by_user_id' => $actor->getKey()]);
            $this->audit->record('event.updated', $locked, $before, $this->snapshot($locked), $actor);
            $this->timeline->record($locked, 'updated', 'Event details updated', $actor);
        });

        return $event->refresh();
    }

    public function archive(Event $event, User $actor, string $reason): void
    {
        DB::transaction(function () use ($event, $actor, $reason) {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            if ($locked->archived_at) {
                throw ValidationException::withMessages(['event' => 'This Event is already archived.']);
            }
            $locked->update([
                'archived_at' => now(),
                'archived_by_user_id' => $actor->getKey(),
                'archive_reason' => $reason,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->timeline->record($locked, 'archived', 'Event archived', $actor, $reason);
            $this->audit->record('event.archived', $locked, ['archived_at' => null], ['archived_at' => $locked->archived_at, 'reason' => $reason], $actor);
        });
        $event->refresh();
    }

    public function reactivate(Event $event, User $actor, ?string $reason): void
    {
        DB::transaction(function () use ($event, $actor, $reason) {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            if (! $locked->archived_at) {
                throw ValidationException::withMessages(['event' => 'This Event is not archived.']);
            }
            $archivedAt = $locked->archived_at;
            $locked->update([
                'archived_at' => null,
                'archived_by_user_id' => null,
                'archive_reason' => null,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->timeline->record($locked, 'reactivated', 'Event reactivated', $actor, $reason);
            $this->audit->record('event.reactivated', $locked, ['archived_at' => $archivedAt], ['archived_at' => null, 'reason' => $reason], $actor);
        });
        $event->refresh();
    }

    /** @return array<string, mixed> */
    public function snapshot(Event $event): array
    {
        return [
            'reference_number' => $event->reference_number,
            'booking_id' => $event->booking_id,
            'name' => $event->name,
            'client_id' => $event->client_id,
            'event_category_id' => $event->event_category_id,
            'event_template_id' => $event->event_template_id,
            'branch_id' => $event->branch_id,
            'manager_user_id' => $event->manager_user_id,
            'status' => $event->status,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'expected_guest_count' => $event->expected_guest_count,
            'core_budget_estimate' => $event->core_budget_estimate,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function ensureBranchAccess(array $attributes, User $actor): void
    {
        $branchId = isset($attributes['branch_id']) ? (int) $attributes['branch_id'] : null;
        if (! $this->branches->permits($actor, $branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'You cannot assign an Event to that branch.']);
        }
    }
}
