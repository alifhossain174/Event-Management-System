<?php

namespace Tests\Feature\Registrations;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Guest;
use App\Models\OutboundMessage;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationForm;
use App\Models\Role;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_public_form_enforces_dynamic_required_rules_and_rejects_option_tampering(): void
    {
        [$event, $form] = $this->publishedForm();
        RegistrationField::query()->create([
            'event_id' => $event->id, 'registration_form_id' => $form->id, 'key' => 'meal',
            'type' => 'select', 'label' => 'Meal', 'options' => ['Vegetarian', 'Standard'],
            'validation_constraints' => [], 'is_required' => true, 'is_active' => true, 'display_order' => 10,
        ]);

        $base = ['idempotency_key' => (string) Str::uuid(), 'registrant_name' => 'Amina Noor', 'registrant_email' => 'amina@example.test'];
        $this->post(route('public.registrations.store', $form->public_slug), $base)
            ->assertSessionHasErrors('responses.meal');
        $this->post(route('public.registrations.store', $form->public_slug), $base + ['idempotency_key' => (string) Str::uuid(), 'responses' => ['meal' => 'Injected option']])
            ->assertSessionHasErrors('responses.meal');
        $this->post(route('public.registrations.store', $form->public_slug), $base + ['idempotency_key' => (string) Str::uuid(), 'responses' => ['meal' => 'Vegetarian']])
            ->assertRedirect();

        $registration = Registration::query()->firstOrFail();
        $this->assertDatabaseHas('registration_responses', [
            'registration_id' => $registration->id, 'field_key' => 'meal', 'field_label' => 'Meal', 'value_text' => 'Vegetarian',
        ]);
        $this->assertSame('pending', $registration->status);
        $this->assertDatabaseHas('registration_status_histories', ['registration_id' => $registration->id, 'to_status' => 'pending', 'actor_type' => 'public']);
    }

    public function test_manager_builds_reorders_and_uses_the_same_definition_for_offline_entry(): void
    {
        $admin = $this->userWithRole('administrator');
        [$event] = $this->publishedForm();

        $this->actingAs($admin)->post(route('events.registration-forms.store', $event), [
            'name' => 'Workshop form', 'duplicate_policy' => 'allow', 'approval_required' => '1',
            'confirmation_channel' => 'none', 'is_active' => '1', 'privacy_text' => 'Used for attendance planning.',
        ])->assertSessionDoesntHaveErrors();
        $form = RegistrationForm::query()->where('name', 'Workshop form')->firstOrFail();

        $this->actingAs($admin)->post(route('events.registration-fields.store', [$event, $form]), [
            'key' => 'unsafe', 'type' => 'html', 'label' => 'Unsafe', 'is_active' => '1',
        ])->assertSessionHasErrors('type');
        foreach ([['topic', 'text', 'Topic'], ['level', 'select', 'Level']] as [$key, $type, $label]) {
            $this->actingAs($admin)->post(route('events.registration-fields.store', [$event, $form]), [
                'key' => $key, 'type' => $type, 'label' => $label, 'is_required' => '1',
                'is_active' => '1', 'options' => $type === 'select' ? "Beginner\nAdvanced" : null,
            ])->assertSessionDoesntHaveErrors();
        }
        $fields = $form->fields()->get();
        $this->actingAs($admin)->put(route('events.registration-fields.reorder', [$event, $form]), [
            'field_ids' => $fields->pluck('id')->all(),
            'positions' => [$fields[0]->id => 20, $fields[1]->id => 10],
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame('level', $form->fields()->first()->key);

        $this->actingAs($admin)->post(route('events.registrations.store', [$event, $form]), [
            'idempotency_key' => (string) Str::uuid(), 'registrant_name' => 'Offline Person',
            'registrant_email' => 'offline@example.test', 'responses' => ['topic' => 'Safety', 'level' => 'Advanced'],
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('registrations', ['registration_form_id' => $form->id, 'source' => 'offline', 'registrant_name' => 'Offline Person']);
    }

    public function test_unpublished_and_disabled_forms_are_not_publicly_accessible(): void
    {
        [$event, $form] = $this->publishedForm();
        $form->update(['published_at' => null]);
        $this->get(route('public.registrations.show', $form->public_slug))->assertNotFound();

        $form->update(['published_at' => now()->subMinute()]);
        $event->moduleSettings()->where('module_key', 'registration')->update(['is_enabled' => false]);
        $this->get(route('public.registrations.show', $form->public_slug))->assertNotFound();
    }

    public function test_duplicate_policy_approval_history_authorization_and_confirmation_are_enforced(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        [$event, $form] = $this->publishedForm(communications: true);
        $form->update(['confirmation_channel' => 'email']);
        RegistrationField::query()->create([
            'event_id' => $event->id, 'registration_form_id' => $form->id, 'key' => 'consent',
            'type' => 'consent', 'label' => 'Privacy consent', 'validation_constraints' => [],
            'is_required' => true, 'is_active' => true, 'display_order' => 10,
        ]);
        $payload = [
            'idempotency_key' => (string) Str::uuid(), 'registrant_name' => 'Mina Das',
            'registrant_email' => 'mina@example.test', 'responses' => ['consent' => '1'],
        ];
        $this->post(route('public.registrations.store', $form->public_slug), $payload)->assertRedirect();
        $registration = Registration::query()->firstOrFail();

        $this->post(route('public.registrations.store', $form->public_slug), array_replace($payload, ['idempotency_key' => (string) Str::uuid()]))
            ->assertSessionHasErrors('registrant_email');
        $this->actingAs($staff)->post(route('events.registrations.review', [$event, $registration]), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($admin)->post(route('events.registrations.review', [$event, $registration]), ['status' => 'approved'])->assertSessionDoesntHaveErrors();

        $this->assertSame('approved', $registration->fresh()->status);
        $this->assertDatabaseCount('registration_status_histories', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.approved']);
        $message = OutboundMessage::query()->where('registration_id', $registration->id)->firstOrFail();
        $this->assertSame('sent', $message->status);
    }

    public function test_guest_conversion_is_explicit_idempotent_and_does_not_require_ticketing(): void
    {
        $admin = $this->userWithRole('administrator');
        [$event, $form] = $this->publishedForm(guests: true);
        EventModuleSetting::query()->updateOrCreate(
            ['event_id' => $event->id, 'module_key' => 'ticketing'],
            ['is_enabled' => false, 'source' => 'manual'],
        );
        $registration = Registration::query()->create([
            'event_id' => $event->id, 'registration_form_id' => $form->id,
            'reference_number' => 'REG-TEST-ONE', 'idempotency_key' => (string) Str::uuid(),
            'source' => 'offline', 'status' => 'approved', 'registrant_name' => 'Farah Khan',
            'registrant_email' => 'farah@example.test', 'normalized_email' => 'farah@example.test',
            'submitted_at' => now(), 'reviewed_by_user_id' => $admin->id, 'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('events.registrations.guest', [$event, $registration]))->assertRedirect();
        $this->actingAs($admin)->post(route('events.registrations.guest', [$event, $registration->fresh()]))->assertRedirect();

        $this->assertDatabaseCount('guests', 1);
        $this->assertNotNull($registration->fresh()->guest_id);
        $guest = Guest::query()->firstOrFail();
        $this->assertSame('registration', $guest->source);
        $this->assertFalse($event->moduleSettings()->where('module_key', 'ticketing')->value('is_enabled'));
    }

    public function test_disabled_module_preserves_data_and_export_is_permission_protected(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        [$event, $form] = $this->publishedForm();
        $registration = Registration::query()->create([
            'event_id' => $event->id, 'registration_form_id' => $form->id,
            'reference_number' => 'REG-EXPORT', 'idempotency_key' => (string) Str::uuid(), 'source' => 'offline',
            'status' => 'pending', 'registrant_name' => 'Export Person', 'submitted_at' => now(),
        ]);
        $this->actingAs($staff)->get(route('events.registrations.export', $event))->assertForbidden();
        $this->actingAs($admin)->get(route('events.registrations.export', $event))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'registration'));

        $event->moduleSettings()->where('module_key', 'registration')->update(['is_enabled' => false]);
        $this->actingAs($admin)->get(route('events.registrations.index', $event))->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    /** @return array{Event, RegistrationForm} */
    private function publishedForm(bool $guests = false, bool $communications = false): array
    {
        $event = Event::factory()->create(['status' => 'planning']);
        foreach (['registration' => true, 'guests' => $guests, 'communications' => $communications] as $key => $enabled) {
            EventModuleSetting::query()->updateOrCreate(
                ['event_id' => $event->id, 'module_key' => $key],
                ['is_enabled' => $enabled, 'source' => 'manual'],
            );
        }
        $form = RegistrationForm::query()->create([
            'event_id' => $event->id, 'name' => 'General registration', 'public_slug' => Str::random(48),
            'duplicate_policy' => 'block_email', 'approval_required' => true,
            'confirmation_channel' => 'none', 'is_active' => true, 'published_at' => now()->subMinute(),
        ]);

        return [$event->fresh('moduleSettings'), $form];
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
