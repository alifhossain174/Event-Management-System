<?php

namespace Tests\Feature\Events;

use App\Models\Client;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            OrganizationSettingsSeeder::class,
            EventConfigurationSeeder::class,
        ]);
    }

    public function test_administrator_can_create_event_directly_without_booking_and_override_template_modules(): void
    {
        $administrator = $this->userWithRole('administrator');
        $client = Client::factory()->create();
        $template = EventTemplate::query()->where('slug', 'birthday-party')->firstOrFail();

        $response = $this->actingAs($administrator)->post(route('events.store'), $this->validPayload([
            'client_id' => $client->id,
            'event_template_id' => $template->id,
            'enabled_modules' => ['venue', 'documents'],
        ]));

        $event = Event::query()->firstOrFail();
        $response->assertRedirect(route('events.show', $event));
        $this->assertNull($event->booking_id);
        $this->assertSame('draft', $event->status);
        $this->assertSame($client->id, $event->client_id);
        $this->assertSame(['documents', 'venue'], collect($event->enabledModuleKeys())->sort()->values()->all());
        $this->assertSame(18, $event->moduleSettings()->count());
        $this->assertSame($template->name, $event->template_snapshot['name']);
        $this->assertDatabaseHas('event_status_histories', [
            'event_id' => $event->id, 'from_status' => null, 'to_status' => 'draft',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.created', 'subject_id' => $event->id]);
    }

    public function test_draft_may_omit_client_but_client_is_required_before_leaving_draft(): void
    {
        $administrator = $this->userWithRole('administrator');
        $this->actingAs($administrator)->post(route('events.store'), $this->validPayload([
            'client_id' => null,
            'enabled_modules' => [],
        ]))->assertSessionDoesntHaveErrors();

        $event = Event::query()->firstOrFail();
        $this->actingAs($administrator)->patch(route('events.status', $event), ['status' => 'confirmed'])
            ->assertSessionHasErrors('client_id');
        $this->assertSame('draft', $event->fresh()->status);

        $client = Client::factory()->create();
        $this->actingAs($administrator)->put(route('events.update', $event), $this->validPayload([
            'client_id' => $client->id,
        ]))->assertRedirect(route('events.show', $event));
        $this->actingAs($administrator)->patch(route('events.status', $event), ['status' => 'confirmed'])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('confirmed', $event->fresh()->status);
    }

    public function test_single_manager_can_complete_every_forward_transition_with_all_optional_modules_disabled(): void
    {
        $administrator = $this->userWithRole('administrator');
        $event = $this->createEvent($administrator, ['enabled_modules' => []]);

        $this->assertSame([], $event->enabledModuleKeys());
        foreach (['confirmed', 'planning', 'in_progress', 'completed'] as $status) {
            $this->actingAs($administrator)->patch(route('events.status', $event), ['status' => $status])
                ->assertSessionDoesntHaveErrors();
            $this->assertSame($status, $event->fresh()->status);
        }

        $this->assertNotNull($event->fresh()->completed_at);
        $this->assertSame(5, $event->statusHistory()->count());
        $this->assertDatabaseHas('event_status_histories', [
            'event_id' => $event->id, 'from_status' => 'in_progress', 'to_status' => 'completed',
            'actor_user_id' => $administrator->id,
        ]);
    }

    public function test_invalid_dates_are_rejected_and_local_time_is_stored_in_utc(): void
    {
        $administrator = $this->userWithRole('administrator');
        $this->actingAs($administrator)->post(route('events.store'), $this->validPayload([
            'starts_at_local' => '2026-12-03T14:00',
            'ends_at_local' => '2026-12-03T13:59',
        ]))->assertSessionHasErrors('ends_at_local');
        $this->assertDatabaseCount('events', 0);

        $this->actingAs($administrator)->post(route('events.store'), $this->validPayload([
            'timezone' => 'Asia/Dhaka',
            'starts_at_local' => '2026-12-03T14:00',
            'ends_at_local' => '2026-12-03T18:00',
        ]))->assertSessionDoesntHaveErrors();
        $event = Event::query()->firstOrFail();
        $this->assertSame('2026-12-03 08:00:00', $event->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Dhaka', $event->timezone);
    }

    public function test_cancellation_records_reason_actor_time_history_timeline_and_audit(): void
    {
        $administrator = $this->userWithRole('administrator');
        $event = $this->createEvent($administrator);

        $this->actingAs($administrator)->patch(route('events.status', $event), ['status' => 'cancelled'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($administrator)->patch(route('events.status', $event), [
            'status' => 'cancelled', 'reason' => 'Client postponed indefinitely.',
        ])->assertSessionDoesntHaveErrors();

        $event->refresh();
        $this->assertSame('cancelled', $event->status);
        $this->assertSame('Client postponed indefinitely.', $event->cancellation_reason);
        $this->assertSame($administrator->id, $event->cancelled_by_user_id);
        $this->assertNotNull($event->cancelled_at);
        $this->assertDatabaseHas('event_status_histories', [
            'event_id' => $event->id, 'from_status' => 'draft', 'to_status' => 'cancelled',
            'actor_user_id' => $administrator->id, 'reason' => 'Client postponed indefinitely.',
        ]);
        $this->assertDatabaseHas('event_timeline_items', ['event_id' => $event->id, 'type' => 'status']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.status_changed', 'subject_id' => $event->id]);
    }

    public function test_cancellation_is_allowed_from_each_non_terminal_planning_state(): void
    {
        $administrator = $this->userWithRole('administrator');

        foreach (['confirmed', 'planning', 'in_progress'] as $origin) {
            $event = $this->createEvent($administrator, ['name' => 'Cancel from '.str($origin)->headline()]);
            foreach (['confirmed', 'planning', 'in_progress'] as $status) {
                $this->actingAs($administrator)->patch(route('events.status', $event), ['status' => $status])
                    ->assertSessionDoesntHaveErrors();
                if ($status === $origin) {
                    break;
                }
            }

            $this->actingAs($administrator)->patch(route('events.status', $event), [
                'status' => 'cancelled', 'reason' => 'Cancelled from '.$origin,
            ])->assertSessionDoesntHaveErrors();
            $this->assertSame('cancelled', $event->fresh()->status);
        }
    }

    public function test_duplication_is_independent_and_copies_only_allowed_planning_data(): void
    {
        $administrator = $this->userWithRole('administrator');
        $source = $this->createEvent($administrator, ['enabled_modules' => ['venue', 'tasks']]);
        $this->actingAs($administrator)->post(route('events.notes.store', $source), [
            'body' => 'Copy this planning note.', 'is_pinned' => 1, 'include_in_duplicate' => 1,
        ])->assertRedirect();
        $this->actingAs($administrator)->post(route('events.notes.store', $source), [
            'body' => 'Keep this source-only note.',
        ])->assertRedirect();
        $sourceHistoryCount = $source->statusHistory()->count();

        $this->actingAs($administrator)->post(route('events.duplicate', $source), [
            'name' => 'Independent Copy', 'copy_notes' => 1,
        ])->assertRedirect();

        $copy = Event::query()->where('source_event_id', $source->id)->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->booking_id);
        $this->assertSame(['tasks', 'venue'], collect($copy->enabledModuleKeys())->sort()->values()->all());
        $this->assertSame(['Copy this planning note.'], $copy->notes()->pluck('body')->all());
        $this->assertSame(1, $copy->statusHistory()->count());
        $this->assertSame(1, $copy->timelineItems()->count());

        $copy->moduleSettings()->where('module_key', 'venue')->update(['is_enabled' => false]);
        $copy->notes()->firstOrFail()->update(['body' => 'Changed only on copy.']);
        $this->assertTrue($source->moduleSettings()->where('module_key', 'venue')->value('is_enabled'));
        $this->assertDatabaseHas('event_notes', ['event_id' => $source->id, 'body' => 'Copy this planning note.']);
        $this->assertSame($sourceHistoryCount, $source->statusHistory()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.duplicated', 'subject_id' => $copy->id]);
    }

    public function test_completed_event_is_read_only_until_administrator_records_privileged_correction(): void
    {
        $administrator = $this->userWithRole('administrator');
        $eventManager = $this->userWithRole('event-manager');
        $event = $this->createEvent($administrator);
        foreach (['confirmed', 'planning', 'in_progress', 'completed'] as $status) {
            $this->actingAs($administrator)->patch(route('events.status', $event), ['status' => $status]);
        }

        $this->actingAs($administrator)->get(route('events.edit', $event))->assertForbidden();
        $this->actingAs($eventManager)->post(route('events.correction', $event), ['reason' => 'Unauthorized attempt'])
            ->assertForbidden();
        $this->actingAs($administrator)->post(route('events.correction', $event), ['reason' => 'Correct the final guest estimate.'])
            ->assertSessionDoesntHaveErrors();

        $event->refresh();
        $this->assertSame('planning', $event->status);
        $this->assertNull($event->completed_at);
        $this->assertDatabaseHas('event_status_histories', [
            'event_id' => $event->id, 'from_status' => 'completed', 'to_status' => 'planning',
            'reason' => 'Correct the final guest estimate.',
        ]);
        $this->actingAs($administrator)->get(route('events.edit', $event))->assertOk();
    }

    public function test_module_disable_requires_confirmation_and_preserves_setting_record(): void
    {
        $administrator = $this->userWithRole('administrator');
        $event = $this->createEvent($administrator, ['enabled_modules' => ['venue', 'tasks']]);
        $settingId = $event->moduleSettings()->where('module_key', 'venue')->value('id');

        $this->actingAs($administrator)->put(route('events.modules.update', $event), [
            'enabled_modules' => ['tasks'],
        ])->assertSessionHasErrors('confirm_disable');
        $this->assertTrue($event->moduleSettings()->where('module_key', 'venue')->value('is_enabled'));

        $this->actingAs($administrator)->put(route('events.modules.update', $event), [
            'enabled_modules' => ['tasks'], 'confirm_disable' => 1,
        ])->assertRedirect(route('events.show', $event));
        $this->assertDatabaseHas('event_module_settings', [
            'id' => $settingId, 'event_id' => $event->id, 'module_key' => 'venue', 'is_enabled' => false,
        ]);
    }

    public function test_archive_and_reactivate_preserve_event_history_without_a_delete_route(): void
    {
        $administrator = $this->userWithRole('administrator');
        $event = $this->createEvent($administrator, ['enabled_modules' => ['documents']]);
        $historyCount = $event->statusHistory()->count();

        $this->actingAs($administrator)->patch(route('events.archive', $event), [
            'action' => 'archive', 'reason' => 'Event retained for long-term records.',
        ])->assertRedirect();
        $this->assertNotNull($event->fresh()->archived_at);
        $this->assertSame($historyCount, $event->statusHistory()->count());
        $this->assertDatabaseHas('event_module_settings', [
            'event_id' => $event->id, 'module_key' => 'documents', 'is_enabled' => true,
        ]);
        $this->actingAs($administrator)->get(route('events.edit', $event))->assertForbidden();
        $this->actingAs($administrator)->delete('/events/'.$event->id)->assertStatus(405);

        $this->actingAs($administrator)->patch(route('events.archive', $event), [
            'action' => 'reactivate', 'reason' => 'Records require another planning update.',
        ])->assertRedirect();
        $this->assertNull($event->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.reactivated', 'subject_id' => $event->id]);
    }

    public function test_event_screens_are_authorized_filterable_paginated_and_include_quick_client_creation(): void
    {
        $administrator = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        Event::factory()->count(21)->create();
        $target = Event::factory()->create(['name' => 'Unique Direct Gala']);

        $this->actingAs($administrator)->get(route('events.index', ['q' => 'Unique Direct Gala']))
            ->assertOk()->assertSee($target->name)->assertViewHas('events', fn ($events) => $events->total() === 1);
        $this->actingAs($administrator)->get(route('events.index'))
            ->assertOk()->assertViewHas('events', fn ($events) => $events->perPage() === 20 && $events->hasPages());
        $this->actingAs($administrator)->get(route('events.create'))
            ->assertOk()->assertSee('Quick create')->assertSee('Optional modules');
        $this->actingAs($staff)->get(route('events.create'))->assertForbidden();
        auth()->logout();
        $this->get(route('events.index'))->assertRedirect(route('login'));
    }

    /** @param array<string, mixed> $overrides */
    private function createEvent(User $actor, array $overrides = []): Event
    {
        $this->actingAs($actor)->post(route('events.store'), $this->validPayload($overrides))
            ->assertSessionDoesntHaveErrors();

        return Event::query()->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Direct Client Event',
            'client_id' => Client::factory()->create()->id,
            'event_category_id' => EventCategory::query()->firstOrFail()->id,
            'starts_at_local' => '2026-12-03T14:00',
            'ends_at_local' => '2026-12-03T18:00',
            'timezone' => 'UTC',
            'primary_contact_name' => 'Nadia Rahman',
            'primary_contact_email' => 'nadia@example.test',
            'expected_guest_count' => 120,
            'core_budget_estimate' => '5000.00',
            'enabled_modules' => [],
        ], $overrides);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
