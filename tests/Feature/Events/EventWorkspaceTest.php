<?php

namespace Tests\Feature\Events;

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventWorkspaceTest extends TestCase
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

    public function test_event_workspace_shows_only_enabled_modules_the_user_may_view(): void
    {
        $administrator = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $event = $this->createEvent($administrator, ['enabled_modules' => ['venue', 'payments']]);

        $this->actingAs($staff)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee(route('events.workspace.module', [$event, 'venue']), false)
            ->assertDontSee(route('events.workspace.module', [$event, 'payments']), false);

        $this->actingAs($administrator)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee(route('events.workspace.module', [$event, 'venue']), false)
            ->assertSee(route('events.workspace.module', [$event, 'payments']), false);
    }

    public function test_disabled_and_unknown_module_routes_fail_safely_without_exposing_workspace_data(): void
    {
        $administrator = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $event = $this->createEvent($administrator, ['enabled_modules' => ['venue']]);

        $this->actingAs($administrator)
            ->get(route('events.workspace.module', [$event, 'documents']))
            ->assertRedirect(route('events.modules.edit', $event))
            ->assertSessionHas('warning');

        $this->actingAs($staff)
            ->get(route('events.workspace.module', [$event, 'documents']))
            ->assertNotFound();

        $this->actingAs($staff)
            ->get(route('events.workspace.module', [$event, 'venue']))
            ->assertOk()
            ->assertSee('Module workspace ready');

        $this->actingAs($administrator)
            ->get(route('events.workspace.module', [$event, 'not-a-module']))
            ->assertNotFound();
    }

    public function test_disabling_a_populated_module_requires_explicit_confirmation_and_reason_then_preserves_and_restores_data(): void
    {
        $administrator = $this->userWithRole('administrator');
        $event = $this->createEvent($administrator, ['enabled_modules' => ['documents']]);
        $settingId = $event->moduleSettings()->where('module_key', 'documents')->value('id');
        $document = Document::query()->create([
            'title' => 'Signed event agreement',
            'status' => 'active',
            'uploaded_by_user_id' => $administrator->getKey(),
        ]);
        DocumentLink::query()->create([
            'document_id' => $document->getKey(),
            'linkable_type' => $event->getMorphClass(),
            'linkable_id' => $event->getKey(),
            'relationship' => 'agreement',
            'created_by_user_id' => $administrator->getKey(),
            'created_at' => now(),
        ]);

        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'documents'));

        $this->actingAs($administrator)->put(route('events.modules.update', $event), [
            'enabled_modules' => [],
            'confirm_disable' => 1,
        ])->assertSessionHasErrors('confirm_disable_with_data');

        $this->actingAs($administrator)->put(route('events.modules.update', $event), [
            'enabled_modules' => [],
            'confirm_disable' => 1,
            'confirm_disable_with_data' => 1,
        ])->assertSessionHasErrors('reason');

        $this->actingAs($administrator)->put(route('events.modules.update', $event), [
            'enabled_modules' => [],
            'confirm_disable' => 1,
            'confirm_disable_with_data' => 1,
            'reason' => 'Documents are not needed during this planning stage.',
        ])->assertRedirect(route('events.show', $event));

        $this->assertDatabaseHas('event_module_settings', [
            'id' => $settingId,
            'event_id' => $event->getKey(),
            'module_key' => 'documents',
            'is_enabled' => false,
        ]);
        $this->assertDatabaseHas('event_module_change_histories', [
            'event_id' => $event->getKey(),
            'module_key' => 'documents',
            'from_enabled' => true,
            'to_enabled' => false,
            'had_data' => true,
            'actor_user_id' => $administrator->getKey(),
            'reason' => 'Documents are not needed during this planning stage.',
        ]);
        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->getKey(),
            'linkable_type' => $event->getMorphClass(),
            'linkable_id' => $event->getKey(),
        ]);

        $this->actingAs($administrator)->put(route('events.modules.update', $event), [
            'enabled_modules' => ['documents'],
            'reason' => 'Restore the preserved document workspace.',
        ])->assertRedirect(route('events.show', $event));

        $this->actingAs($administrator)
            ->get(route('events.workspace.module', [$event, 'documents']))
            ->assertOk();
        $this->assertDatabaseCount('event_module_change_histories', 2);
        $this->assertDatabaseHas('document_links', ['document_id' => $document->getKey()]);
    }

    public function test_manage_modules_is_manager_only_and_exposes_origin_data_warnings_and_filterable_history(): void
    {
        $administrator = $this->userWithRole('administrator');
        $eventManager = $this->userWithRole('event-manager');
        $staff = $this->userWithRole('staff');
        $event = $this->createEvent($administrator, ['enabled_modules' => ['tasks']]);

        $this->actingAs($staff)->get(route('events.modules.edit', $event))->assertForbidden();
        $this->actingAs($eventManager)->get(route('events.modules.edit', $event))
            ->assertOk()
            ->assertSee('Manual origin')
            ->assertSee('Module change history');

        $this->actingAs($administrator)->put(route('events.modules.update', $event), [
            'enabled_modules' => [],
            'confirm_disable' => 1,
            'reason' => 'Use only the core workflow.',
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($eventManager)->get(route('events.modules.edit', [
            'event' => $event,
            'history_module' => 'tasks',
            'history_action' => 'disabled',
        ]))->assertOk()
            ->assertSee('Use only the core workflow.')
            ->assertViewHas('history', fn ($history) => $history->total() === 1);
    }

    /** @param array<string, mixed> $overrides */
    private function createEvent(User $actor, array $overrides = []): Event
    {
        $this->actingAs($actor)->post(route('events.store'), array_replace([
            'name' => 'Workspace Event',
            'client_id' => Client::factory()->create()->id,
            'event_category_id' => EventCategory::query()->firstOrFail()->id,
            'starts_at_local' => '2026-12-03T14:00',
            'ends_at_local' => '2026-12-03T18:00',
            'timezone' => 'UTC',
            'enabled_modules' => [],
        ], $overrides))->assertSessionDoesntHaveErrors();

        return Event::query()->latest('id')->firstOrFail();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
