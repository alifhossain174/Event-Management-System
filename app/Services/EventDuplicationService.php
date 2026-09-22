<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EventDuplicationService
{
    public function __construct(
        private readonly EventReferenceService $references,
        private readonly EventTimelineService $timeline,
        private readonly AuditService $audit,
    ) {}

    public function duplicate(Event $source, User $actor, string $name, bool $copyNotes): Event
    {
        return DB::transaction(function () use ($source, $actor, $name, $copyNotes) {
            $source->loadMissing(['moduleSettings', 'notes']);
            $copy = Event::query()->create([
                'reference_number' => $this->references->next(),
                'source_event_id' => $source->getKey(),
                'client_id' => $source->client_id,
                'booking_id' => null,
                'event_category_id' => $source->event_category_id,
                'event_template_id' => $source->event_template_id,
                'branch_id' => $source->branch_id,
                'manager_user_id' => $source->manager_user_id,
                'name' => $name,
                'status' => 'draft',
                'starts_at' => $source->starts_at,
                'ends_at' => $source->ends_at,
                'timezone' => $source->timezone,
                'primary_contact_name' => $source->primary_contact_name,
                'primary_contact_email' => $source->primary_contact_email,
                'primary_contact_phone' => $source->primary_contact_phone,
                'expected_guest_count' => $source->expected_guest_count,
                'theme' => $source->theme,
                'dress_code' => $source->dress_code,
                'description' => $source->description,
                'core_budget_estimate' => $source->core_budget_estimate,
                'currency_code' => $source->currency_code,
                'template_snapshot' => $source->template_snapshot,
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            foreach ($source->moduleSettings as $setting) {
                $copy->moduleSettings()->create([
                    'module_key' => $setting->module_key,
                    'is_enabled' => $setting->is_enabled,
                    'source' => 'duplicate',
                    'enabled_by_user_id' => $setting->is_enabled ? $actor->getKey() : null,
                    'enabled_at' => $setting->is_enabled ? now() : null,
                    'disabled_by_user_id' => $setting->is_enabled ? null : $actor->getKey(),
                    'disabled_at' => $setting->is_enabled ? null : now(),
                ]);
            }

            if ($copyNotes) {
                foreach ($source->notes->where('include_in_duplicate', true) as $note) {
                    $copy->notes()->create([
                        'duplicated_from_note_id' => $note->getKey(),
                        'body' => $note->body,
                        'is_pinned' => $note->is_pinned,
                        'include_in_duplicate' => true,
                        'created_by_user_id' => $actor->getKey(),
                    ]);
                }
            }

            EventStatusHistory::query()->create([
                'event_id' => $copy->getKey(),
                'from_status' => null,
                'to_status' => 'draft',
                'actor_type' => 'user',
                'actor_user_id' => $actor->getKey(),
                'reason' => 'Duplicated from '.$source->reference_number,
                'metadata' => ['source_event_id' => $source->getKey()],
                'changed_at' => now(),
            ]);
            $this->timeline->record($copy, 'created', 'Event duplicated', $actor, null, ['source_event_id' => $source->getKey()]);
            $this->audit->record('event.duplicated', $copy, [], [
                'source_event_id' => $source->getKey(),
                'copied_notes' => $copyNotes,
                'enabled_modules' => $copy->enabledModuleKeys(),
                'booking_id' => null,
            ], $actor);

            return $copy->fresh(['moduleSettings.definition', 'notes']);
        });
    }
}
