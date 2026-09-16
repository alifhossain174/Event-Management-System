<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_has_every_configured_permission_and_user_route_access(): void
    {
        $administrator = $this->userWithRole('administrator');
        $target = $this->userWithRole('staff');

        foreach (array_keys(config('rbac.permissions')) as $permission) {
            $this->assertTrue(Gate::forUser($administrator)->allows($permission), $permission);
        }

        $this->actingAs($administrator)->get(route('users.index'))->assertOk();
        $this->actingAs($administrator)->get(route('users.create'))->assertOk();
        $this->actingAs($administrator)->get(route('users.show', $target))->assertOk();
        $this->actingAs($administrator)->get(route('users.edit', $target))->assertOk();
    }

    public function test_unauthorized_user_cannot_access_user_management_directly(): void
    {
        $staff = $this->userWithRole('staff');
        $target = User::factory()->create();

        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('users.edit', $target))->assertForbidden();
        $this->actingAs($staff)->put(route('users.update', $target), [
            'name' => 'Changed',
            'email' => $target->email,
            'role_ids' => [Role::where('slug', 'staff')->value('id')],
        ])->assertForbidden();
    }

    public function test_administrator_can_create_user_and_assign_roles_with_audit_history(): void
    {
        $administrator = $this->userWithRole('administrator');
        $eventManager = Role::where('slug', 'event-manager')->firstOrFail();

        $response = $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'Event Manager',
            'email' => 'manager@example.test',
            'password' => 'Secure-password-123',
            'password_confirmation' => 'Secure-password-123',
            'role_ids' => [$eventManager->id],
            'is_active' => '1',
        ]);

        $created = User::where('email', 'manager@example.test')->firstOrFail();
        $response->assertRedirect(route('users.show', $created));
        $this->assertTrue($created->hasRole('event-manager'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'actor_user_id' => $administrator->id,
            'subject_id' => $created->id,
        ]);
    }

    public function test_administrator_can_change_role_and_deactivate_user(): void
    {
        $administrator = $this->userWithRole('administrator');
        $target = $this->userWithRole('staff');
        $eventManager = Role::where('slug', 'event-manager')->firstOrFail();

        $this->actingAs($administrator)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_ids' => [$eventManager->id],
        ])->assertRedirect(route('users.show', $target));

        $this->assertTrue($target->fresh()->hasRole('event-manager'));

        $this->actingAs($administrator)->patch(route('users.status.update', $target), [
            'status' => 'inactive',
            'reason' => 'Assignment ended',
        ])->assertSessionHas('status');

        $this->assertFalse($target->fresh()->is_active);
        $this->assertDatabaseHas('user_status_histories', [
            'user_id' => $target->id,
            'from_status' => 'active',
            'to_status' => 'inactive',
            'actor_user_id' => $administrator->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deactivated',
            'subject_id' => $target->id,
        ]);
    }

    public function test_user_list_is_filterable_and_paginated(): void
    {
        $administrator = $this->userWithRole('administrator');
        User::factory()->count(16)->create();
        User::factory()->create(['name' => 'Unique Search Name']);

        $this->actingAs($administrator)
            ->get(route('users.index', ['q' => 'Unique Search']))
            ->assertOk()
            ->assertSee('Unique Search Name')
            ->assertDontSee(User::where('name', '!=', 'Unique Search Name')->first()->email);

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('pagination');
    }

    public function test_development_administrator_can_be_seeded_from_uncommitted_environment_configuration(): void
    {
        config()->set('rbac.development_administrator', [
            'name' => 'Local Administrator',
            'email' => 'local-admin@example.test',
            'password' => 'Environment-only-password-123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $administrator = User::where('email', 'local-admin@example.test')->firstOrFail();
        $this->assertTrue($administrator->is_active);
        $this->assertTrue($administrator->hasRole('administrator'));
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', $slug)->firstOrFail();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
