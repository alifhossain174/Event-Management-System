<?php

namespace Tests\Feature\Clients;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class]);
    }

    public function test_manager_can_create_individual_without_user_and_values_are_normalized(): void
    {
        $administrator = $this->userWithRole('administrator');

        $response = $this->actingAs($administrator)->post(route('clients.store'), [
            'type' => 'individual',
            'first_name' => '  Ayesha  ',
            'last_name' => ' Rahman ',
            'primary_email' => 'AYESHA@EXAMPLE.TEST',
            'primary_phone' => '+880 (171) 234-5678',
            'country_code' => 'bd',
        ]);

        $client = Client::query()->firstOrFail();
        $response->assertRedirect(route('clients.show', $client));
        $this->assertNull($client->user_id);
        $this->assertSame('Ayesha Rahman', $client->display_name);
        $this->assertSame('ayesha@example.test', $client->normalized_email);
        $this->assertSame('8801712345678', $client->normalized_phone);
        $this->assertSame('BD', $client->country_code);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client.created',
            'subject_id' => $client->id,
            'actor_user_id' => $administrator->id,
        ]);
        $this->actingAs($administrator)->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Client details')
            ->assertSee('Recorded in Event workspaces');
    }

    public function test_individual_and_organization_fields_are_validated_by_type(): void
    {
        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)->post(route('clients.store'), [
            'type' => 'individual', 'primary_email' => 'person@example.test',
        ])->assertSessionHasErrors('first_name');

        $this->actingAs($administrator)->post(route('clients.store'), [
            'type' => 'organization', 'primary_email' => 'org@example.test',
        ])->assertSessionHasErrors('organization_name');

        $this->actingAs($administrator)->post(route('clients.store'), [
            'type' => 'organization',
            'organization_name' => 'North Star Events Ltd',
            'first_name' => 'Must be cleared',
            'primary_email' => 'org@example.test',
        ])->assertSessionDoesntHaveErrors();

        $organization = Client::query()->where('type', 'organization')->firstOrFail();
        $this->assertSame('North Star Events Ltd', $organization->display_name);
        $this->assertNull($organization->first_name);
    }

    public function test_possible_duplicates_warn_but_shared_contact_details_are_not_rejected(): void
    {
        $administrator = $this->userWithRole('administrator');
        Client::factory()->create([
            'display_name' => 'Rahman Household One',
            'normalized_name' => 'rahman household one',
            'primary_email' => 'family@example.test',
            'normalized_email' => 'family@example.test',
        ]);

        $response = $this->actingAs($administrator)->post(route('clients.store'), [
            'type' => 'individual',
            'first_name' => 'Nadia',
            'last_name' => 'Rahman',
            'primary_email' => 'family@example.test',
        ]);

        $response->assertSessionHas('warning');
        $this->assertDatabaseHas('clients', ['display_name' => 'Nadia Rahman']);
        $this->assertSame(2, Client::query()->where('normalized_email', 'family@example.test')->count());
    }

    public function test_client_list_is_filterable_paginated_and_branch_scoped_when_enabled(): void
    {
        $administrator = $this->userWithRole('administrator');
        Client::factory()->count(22)->create();
        Client::factory()->organization()->create(['organization_name' => 'Unique Atlas Limited', 'display_name' => 'Unique Atlas Limited', 'normalized_name' => 'unique atlas limited']);

        $this->actingAs($administrator)->get(route('clients.index', ['q' => 'Unique Atlas', 'type' => 'organization']))
            ->assertOk()->assertSee('Unique Atlas Limited');
        $this->actingAs($administrator)->get(route('clients.index'))->assertOk()->assertSee('pagination');

        $branchA = Branch::query()->create(['company_id' => 1, 'name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $branchB = Branch::query()->create(['company_id' => 1, 'name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
        $manager = $this->userWithRole('event-manager');
        $manager->branches()->attach($branchA);
        $visible = Client::factory()->create(['branch_id' => $branchA->id, 'display_name' => 'Visible Client', 'normalized_name' => 'visible client']);
        $hidden = Client::factory()->create(['branch_id' => $branchB->id, 'display_name' => 'Hidden Client', 'normalized_name' => 'hidden client']);
        app(SettingsService::class)->update(['features.branches_enabled' => true], $administrator);

        $this->actingAs($manager)->get(route('clients.index', ['q' => 'Visible Client']))
            ->assertOk()->assertSee($visible->display_name)->assertDontSee('Hidden Client');
        $this->actingAs($manager)->get(route('clients.index', ['q' => 'Hidden Client']))
            ->assertOk()->assertDontSee(route('clients.show', $hidden), false);
        $this->actingAs($manager)->post(route('clients.store'), [
            'type' => 'individual', 'first_name' => 'Wrong Branch',
            'primary_email' => 'wrong@example.test', 'branch_id' => $branchB->id,
        ])->assertSessionHasErrors('branch_id');
    }

    public function test_contacts_can_share_channels_and_primary_contact_is_managed(): void
    {
        $administrator = $this->userWithRole('administrator');
        $client = Client::factory()->create();

        foreach (['First Contact', 'Second Contact'] as $name) {
            $this->actingAs($administrator)->post(route('clients.contacts.store', $client), [
                'name' => $name,
                'email' => 'shared@example.test',
                'is_primary' => true,
            ])->assertRedirect();
        }

        $this->assertSame(2, ClientContact::query()->where('normalized_email', 'shared@example.test')->count());
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
        $contact = $client->contacts()->where('name', 'First Contact')->firstOrFail();
        $this->actingAs($administrator)->delete(route('clients.contacts.destroy', [$client, $contact]))->assertRedirect();
        $this->assertSoftDeleted($contact);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.contact_archived', 'subject_id' => $client->id]);
    }

    public function test_archive_and_reactivate_preserve_record_contacts_and_status_history_without_delete_route(): void
    {
        $administrator = $this->userWithRole('administrator');
        $client = Client::factory()->create();
        $contact = ClientContact::factory()->for($client)->create();

        $this->actingAs($administrator)->patch(route('clients.status', $client), [
            'action' => 'archive', 'reason' => 'No current work',
        ])->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'status' => 'archived']);
        $this->assertDatabaseHas('client_contacts', ['id' => $contact->id, 'client_id' => $client->id]);
        $this->assertDatabaseHas('status_histories', [
            'subject_type' => $client->getMorphClass(), 'subject_id' => $client->id,
            'from_status' => 'active', 'to_status' => 'archived', 'actor_user_id' => $administrator->id,
        ]);
        $this->actingAs($administrator)->delete('/clients/'.$client->id)->assertStatus(405);

        $this->actingAs($administrator)->patch(route('clients.status', $client), [
            'action' => 'reactivate', 'reason' => 'New enquiry',
        ])->assertRedirect(route('clients.show', $client));
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'status' => 'active']);
    }

    public function test_authorization_allows_manager_workflow_but_denies_staff_and_privileged_actions(): void
    {
        $manager = $this->userWithRole('event-manager');
        $staff = $this->userWithRole('staff');
        $client = Client::factory()->create();

        $this->actingAs($manager)->get(route('clients.index'))->assertOk();
        $this->actingAs($manager)->get(route('clients.edit', $client))->assertOk();
        $this->actingAs($manager)->patch(route('clients.status', $client), [
            'action' => 'archive', 'reason' => 'Attempt',
        ])->assertForbidden();

        $this->actingAs($staff)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('clients.show', $client))->assertForbidden();
        $this->actingAs($staff)->post(route('clients.quick-create'), [
            'type' => 'individual', 'first_name' => 'Denied', 'primary_email' => 'denied@example.test',
        ])->assertForbidden();
    }

    public function test_lookup_and_quick_create_support_future_event_forms_with_shared_validation(): void
    {
        $manager = $this->userWithRole('event-manager');
        Client::factory()->create(['display_name' => 'Lookup Target', 'normalized_name' => 'lookup target']);

        $this->actingAs($manager)->getJson(route('clients.lookup', ['q' => 'Lookup']))
            ->assertOk()->assertJsonPath('data.0.text', 'Lookup Target');

        $this->actingAs($manager)->postJson(route('clients.quick-create'), [
            'type' => 'individual', 'primary_email' => 'invalid@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors('first_name');

        $response = $this->actingAs($manager)->postJson(route('clients.quick-create'), [
            'type' => 'individual', 'first_name' => 'Quick Client', 'primary_email' => 'quick@example.test',
        ])->assertCreated()->assertJsonPath('data.text', 'Quick Client');
        $client = Client::query()->findOrFail($response->json('data.id'));
        $this->assertNull($client->user_id);
    }

    public function test_reusable_event_form_selector_renders_permission_aware_quick_create_flow(): void
    {
        $administrator = $this->userWithRole('administrator');
        $clients = Client::factory()->count(2)->create();
        $this->actingAs($administrator);
        $this->withViewErrors([]);

        $html = Blade::render('<x-clients.selector :clients="$clients" />', compact('clients'));

        $this->assertStringContainsString('name="client_id"', $html);
        $this->assertStringContainsString(route('clients.quick-create'), $html);
        $this->assertStringContainsString('Quick create', $html);
        $this->assertStringContainsString(e($clients->first()->display_name), $html);
    }

    public function test_administrator_can_link_portal_user_and_merge_duplicates_with_audit_history(): void
    {
        $administrator = $this->userWithRole('administrator');
        $portalUser = $this->userWithRole('client');
        $source = Client::factory()->create(['display_name' => 'Duplicate Source', 'normalized_name' => 'duplicate source']);
        $target = Client::factory()->create(['display_name' => 'Canonical Target', 'normalized_name' => 'canonical target']);
        $contact = ClientContact::factory()->for($source)->create();

        $this->actingAs($administrator)->put(route('clients.user-link', $target), ['user_id' => $portalUser->id])
            ->assertRedirect();
        $this->assertSame($portalUser->id, $target->fresh()->user_id);

        $this->actingAs($administrator)->post(route('clients.merge', $source), [
            'target_client_id' => $target->id, 'reason' => 'Confirmed duplicate',
        ])->assertRedirect(route('clients.show', $target));

        $this->assertDatabaseHas('clients', [
            'id' => $source->id, 'status' => 'merged', 'merged_into_client_id' => $target->id,
        ]);
        $this->assertDatabaseHas('client_contacts', ['id' => $contact->id, 'client_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.user_link_changed', 'subject_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.merged', 'subject_id' => $source->id]);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
