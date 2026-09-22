<?php

namespace Tests\Feature\Events;

use App\Models\EventTemplate;
use App\Models\ModuleDefinition;
use App\Models\Role;
use App\Models\User;
use App\Services\EventModuleService;
use App\Services\EventTemplateService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventTemplateManagementTest extends TestCase
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

    public function test_registry_and_srs_starting_templates_are_seeded_as_editable_data(): void
    {
        $this->assertSame(29, ModuleDefinition::query()->count());
        $this->assertSame(18, ModuleDefinition::query()->where('is_event_scoped', true)->count());
        $this->assertSame(5, EventTemplate::query()->count());
        $this->assertEqualsCanonicalizing([
            'Birthday Party',
            'Wedding/Marriage Ceremony',
            'Corporate/Seminar/Workshop',
            'Concert/Exhibition/Festival',
            'Custom Event',
        ], EventTemplate::query()->pluck('name')->all());

        $birthday = EventTemplate::query()->where('slug', 'birthday-party')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['venue', 'catering', 'decoration', 'vendors', 'staff', 'tasks', 'budget', 'payments', 'invoices'],
            $birthday->modules()->where('recommendation', 'default')->pluck('module_key')->all(),
        );

        $custom = EventTemplate::query()->where('slug', 'custom-event')->firstOrFail();
        $this->assertSame([], $custom->modules()->pluck('module_key')->all());
        $this->assertSame([], app(EventModuleService::class)->initializationPlan($custom)->enabledKeys());
    }

    public function test_template_edits_do_not_change_an_existing_initialization_snapshot(): void
    {
        $actor = $this->userWithRole('administrator');
        $template = EventTemplate::query()->where('slug', 'birthday-party')->firstOrFail();
        $modules = app(EventModuleService::class);
        $existingEventSnapshot = $modules->initializationPlan($template);

        app(EventTemplateService::class)->update($template, [
            'name' => $template->name,
            'slug' => $template->slug,
            'event_category_id' => $template->event_category_id,
            'description' => $template->description,
            'service_notes' => 'Revised defaults for future Events only.',
            'starter_tasks' => [['title' => 'Confirm brief']],
            'budget_lines' => [['direction' => 'expense', 'label' => 'Venue estimate', 'amount' => '0.00']],
            'module_recommendations' => ['documents' => 'default'],
        ], $actor);

        $this->assertContains('venue', $existingEventSnapshot->enabledKeys());
        $this->assertNotContains('documents', $existingEventSnapshot->enabledKeys());
        $this->assertSame([], $existingEventSnapshot->starterTasks);

        $futureEventSnapshot = $modules->initializationPlan($template->fresh());
        $this->assertSame(['documents'], $futureEventSnapshot->enabledKeys());
        $this->assertSame([['title' => 'Confirm brief']], $futureEventSnapshot->starterTasks);
    }

    public function test_unknown_or_global_module_keys_fail_safely(): void
    {
        $service = app(EventModuleService::class);

        foreach (['not-a-module', 'dashboard'] as $key) {
            try {
                $service->initializationPlan(null, [$key => true]);
                $this->fail("The {$key} module key should have failed validation.");
            } catch (ValidationException $exception) {
                $this->assertStringContainsString($key, $exception->errors()['modules'][0]);
            }
        }

        ModuleDefinition::query()->where('key', 'venue')->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $service->initializationPlan(null, ['venue' => true]);
    }

    public function test_all_specialized_modules_can_stay_disabled_and_dependencies_are_warnings_only(): void
    {
        $service = app(EventModuleService::class);
        $blank = $service->initializationPlan();

        $this->assertCount(18, $blank->moduleStates);
        $this->assertSame([], $blank->enabledKeys());

        $guestOnly = $service->initializationPlan(null, ['guests' => true]);
        $this->assertSame(['guests'], $guestOnly->enabledKeys());
        $this->assertNotEmpty($guestOnly->warnings);
    }

    public function test_administrator_can_create_duplicate_archive_and_reactivate_a_template_with_audit_history(): void
    {
        $admin = $this->userWithRole('administrator');

        $this->actingAs($admin)->post(route('settings.event-templates.store'), [
            'name' => 'Private Dinner',
            'slug' => 'private-dinner',
            'description' => 'Reusable dinner setup.',
            'service_notes' => 'Confirm accessibility requirements.',
            'module_recommendations' => ['venue' => 'default', 'catering' => 'optional'],
            'starter_tasks_text' => "Confirm date\nConfirm menu",
            'budget_lines_text' => "expense|Venue estimate|0.00\nincome|Client estimate|1000.00",
        ])->assertRedirect();

        $template = EventTemplate::query()->where('slug', 'private-dinner')->firstOrFail();
        $this->assertSame(2, $template->modules()->count());
        $this->assertCount(2, $template->starter_tasks);

        $this->actingAs($admin)->post(route('settings.event-templates.duplicate', $template))->assertRedirect();
        $copy = EventTemplate::query()->where('source_template_id', $template->id)->firstOrFail();
        $this->assertSame(2, $copy->modules()->count());

        $this->actingAs($admin)->patch(route('settings.event-templates.status', $copy), [
            'action' => 'archive',
            'reason' => 'No longer offered',
        ])->assertRedirect();
        $this->assertDatabaseHas('status_histories', [
            'subject_type' => $copy->getMorphClass(),
            'subject_id' => $copy->id,
            'from_status' => 'active',
            'to_status' => 'archived',
        ]);

        $this->actingAs($admin)->patch(route('settings.event-templates.status', $copy), [
            'action' => 'reactivate',
        ])->assertRedirect();
        $this->assertSame('active', $copy->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event-template.duplicated']);
    }

    public function test_module_display_configuration_preserves_stable_key_and_scope(): void
    {
        $admin = $this->userWithRole('administrator');
        $definition = ModuleDefinition::query()->where('key', 'venue')->firstOrFail();

        $this->actingAs($admin)->put(route('settings.module-definitions.update', $definition), [
            'label' => 'Locations',
            'sort_order' => 35,
            'is_active' => 1,
            'key' => 'changed-key',
            'scope' => 'global',
        ])->assertRedirect();

        $definition->refresh();
        $this->assertSame('venue', $definition->key);
        $this->assertSame('event_scoped', $definition->scope);
        $this->assertSame('Locations', $definition->label);
        $this->assertDatabaseHas('audit_logs', ['action' => 'module-definition.updated']);
    }

    public function test_event_manager_can_view_but_not_configure_and_unprivileged_roles_are_denied(): void
    {
        $eventManager = $this->userWithRole('event-manager');
        $staff = $this->userWithRole('staff');
        $template = EventTemplate::query()->firstOrFail();

        $this->actingAs($eventManager)->get(route('settings.event-templates.index'))->assertOk();
        $this->actingAs($eventManager)->get(route('settings.event-templates.show', $template))->assertOk();
        $this->actingAs($eventManager)->get(route('settings.event-templates.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.event-templates.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.module-definitions.index'))->assertForbidden();
    }

    public function test_administration_screens_render_with_filters_and_bounded_pagination(): void
    {
        $admin = $this->userWithRole('administrator');
        EventTemplate::factory()->count(16)->create();
        $template = EventTemplate::query()->where('slug', 'birthday-party')->firstOrFail();
        $definition = ModuleDefinition::query()->where('key', 'venue')->firstOrFail();

        $this->actingAs($admin)->get(route('settings.event-templates.index'))
            ->assertOk()
            ->assertViewHas('templates', fn ($templates) => $templates->perPage() === 15 && $templates->hasPages());
        $this->actingAs($admin)->get(route('settings.event-templates.index', ['q' => 'Custom Event']))
            ->assertOk()
            ->assertViewHas('templates', fn ($templates) => $templates->total() === 1);
        $this->actingAs($admin)->get(route('settings.event-templates.create'))->assertOk();
        $this->actingAs($admin)->get(route('settings.event-templates.show', $template))->assertOk();
        $this->actingAs($admin)->get(route('settings.event-templates.edit', $template))->assertOk();

        $this->actingAs($admin)->get(route('settings.module-definitions.index', ['scope' => 'event_scoped']))
            ->assertOk()
            ->assertViewHas('definitions', fn ($definitions) => $definitions->total() === 18 && $definitions->perPage() === 20);
        $this->actingAs($admin)->get(route('settings.module-definitions.edit', $definition))->assertOk();
    }

    public function test_rerunning_the_seeder_preserves_administrator_edits(): void
    {
        EventTemplate::query()->where('slug', 'birthday-party')->update(['name' => 'Birthday Celebration']);
        ModuleDefinition::query()->where('key', 'venue')->update(['label' => 'Locations']);

        $this->seed(EventConfigurationSeeder::class);

        $this->assertDatabaseHas('event_templates', ['slug' => 'birthday-party', 'name' => 'Birthday Celebration']);
        $this->assertDatabaseHas('module_definitions', ['key' => 'venue', 'label' => 'Locations']);
        $this->assertSame(5, EventTemplate::query()->count());
        $this->assertSame(29, ModuleDefinition::query()->count());
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
