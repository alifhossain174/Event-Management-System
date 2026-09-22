<?php

namespace App\Services;

use App\Data\EventModulePlan;
use App\Models\Event;
use App\Models\EventModuleChangeHistory;
use App\Models\EventModuleSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EventModuleSettingService
{
    public function __construct(
        private readonly EventModuleService $modules,
        private readonly AuditService $audit,
        private readonly EventTimelineService $timeline,
        private readonly EventModuleDataRegistry $dataDetectors,
    ) {}

    public function initialize(Event $event, EventModulePlan $plan, User $actor, string $source): void
    {
        foreach ($plan->moduleStates as $key => $enabled) {
            EventModuleSetting::query()->create([
                'event_id' => $event->getKey(),
                'module_key' => $key,
                'is_enabled' => $enabled,
                'source' => $source,
                'origin' => $source,
                'enabled_by_user_id' => $enabled ? $actor->getKey() : null,
                'enabled_at' => $enabled ? now() : null,
                'disabled_by_user_id' => $enabled ? null : $actor->getKey(),
                'disabled_at' => $enabled ? null : now(),
            ]);
        }
    }

    /** @param list<string> $enabledKeys */
    public function update(
        Event $event,
        array $enabledKeys,
        User $actor,
        bool $confirmDisable,
        bool $confirmDisableWithData,
        ?string $reason,
    ): void {
        if ($event->isOperationallyReadOnly()) {
            throw ValidationException::withMessages(['modules' => 'Completed, cancelled, or archived Events cannot change modules. Reopen a completed Event through correction first.']);
        }

        $enabledKeys = $this->modules->validateModuleKeys($enabledKeys);
        $this->modules->initializationPlan(null, array_fill_keys($enabledKeys, true));

        DB::transaction(function () use ($event, $enabledKeys, $actor, $confirmDisable, $confirmDisableWithData, $reason) {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $settings = $locked->moduleSettings()->lockForUpdate()->get()->keyBy('module_key');
            $before = $settings->where('is_enabled', true)->keys()->values()->all();
            $toDisable = array_values(array_diff($before, $enabledKeys));

            if ($toDisable !== [] && ! $confirmDisable) {
                throw ValidationException::withMessages([
                    'confirm_disable' => 'Confirm module disablement. Existing module data will be preserved and restored if re-enabled.',
                ]);
            }

            $dataPresence = $this->dataDetectors->presenceFor($locked, $toDisable);
            $populatedToDisable = array_keys(array_filter($dataPresence));

            if ($populatedToDisable !== [] && ! $confirmDisableWithData) {
                throw ValidationException::withMessages([
                    'confirm_disable_with_data' => 'Explicitly confirm disabling modules that already contain data. The data will remain stored and accessible again after re-enabling.',
                ]);
            }

            if ($populatedToDisable !== [] && blank($reason)) {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required when disabling a module that contains data.',
                ]);
            }

            foreach ($this->modules->eventScopedKeys() as $key) {
                $enabled = in_array($key, $enabledKeys, true);
                $setting = $settings->get($key) ?? new EventModuleSetting([
                    'event_id' => $locked->getKey(),
                    'module_key' => $key,
                    'origin' => 'manual',
                ]);
                $fromEnabled = $setting->exists ? $setting->is_enabled : false;

                if ($setting->exists && $setting->is_enabled === $enabled) {
                    continue;
                }

                $setting->fill([
                    'is_enabled' => $enabled,
                    'source' => 'manual',
                    'enabled_by_user_id' => $enabled ? $actor->getKey() : $setting->enabled_by_user_id,
                    'enabled_at' => $enabled ? now() : $setting->enabled_at,
                    'disabled_by_user_id' => $enabled ? null : $actor->getKey(),
                    'disabled_at' => $enabled ? null : now(),
                ])->save();

                EventModuleChangeHistory::query()->create([
                    'event_id' => $locked->getKey(),
                    'module_key' => $key,
                    'from_enabled' => $fromEnabled,
                    'to_enabled' => $enabled,
                    'had_data' => $this->dataDetectors->hasData($locked, $key),
                    'actor_user_id' => $actor->getKey(),
                    'reason' => $reason,
                    'changed_at' => now(),
                ]);
            }

            $after = $locked->moduleSettings()->where('is_enabled', true)->pluck('module_key')->all();
            $this->audit->record('event.modules_updated', $locked, ['enabled_modules' => $before], [
                'enabled_modules' => $after,
                'populated_modules_disabled' => $populatedToDisable,
                'reason' => $reason,
            ], $actor);
            $this->timeline->record($locked, 'modules', 'Event modules updated', $actor, $reason, [
                'enabled' => array_values(array_diff($after, $before)),
                'disabled' => $toDisable,
            ]);
        });

        $event->refresh();
    }
}
