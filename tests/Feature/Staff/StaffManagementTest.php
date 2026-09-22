<?php

namespace Tests\Feature\Staff;

use App\Models\Department;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class]);
    }

    public function test_administrator_creates_staff_profile_without_login_and_department_is_configurable(): void
    {
        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->post(route('settings.master-data.store', 'departments'), ['name' => 'Operations', 'slug' => 'operations', 'description' => null, 'is_active' => 1, 'sort_order' => 0])->assertRedirect();
        $department = Department::query()->firstOrFail();
        $this->actingAs($admin)->post(route('staff.store'), ['first_name' => '  Mina ', 'last_name' => 'Rahman ', 'email' => 'MINA@EXAMPLE.TEST', 'department_id' => $department->id, 'employment_status' => 'active', 'role_title' => 'Coordinator'])->assertRedirect();
        $staff = StaffProfile::query()->firstOrFail();
        $this->assertNull($staff->user_id);
        $this->assertSame('Mina Rahman', $staff->display_name);
        $this->assertSame('mina@example.test', $staff->normalized_email);
        $this->assertSame($department->id, $staff->department_id);
    }

    public function test_linked_staff_user_sees_only_own_profile_and_cannot_edit_it(): void
    {
        $admin = $this->userWithRole('administrator');
        $portal = $this->userWithRole('staff');
        $own = StaffProfile::factory()->create();
        $other = StaffProfile::factory()->create(['display_name' => 'Other Staff', 'normalized_name' => 'other staff']);
        $this->actingAs($admin)->put(route('staff.user-link', $own), ['user_id' => $portal->id])->assertRedirect();
        $this->actingAs($portal)->get(route('staff.index'))->assertOk()->assertSee($own->display_name)->assertDontSee('Other Staff');
        $this->actingAs($portal)->get(route('staff.show', $own))->assertOk();
        $this->actingAs($portal)->get(route('staff.show', $other))->assertForbidden();
        $this->actingAs($portal)->get(route('staff.edit', $own))->assertForbidden();
    }

    public function test_archive_preserves_profile_user_link_and_status_history_after_user_deactivation(): void
    {
        $admin = $this->userWithRole('administrator');
        $portal = $this->userWithRole('staff');
        $profile = StaffProfile::factory()->create(['user_id' => $portal->id]);
        $portal->update(['is_active' => false, 'deactivated_at' => now()]);
        $this->actingAs($admin)->patch(route('staff.status', $profile), ['action' => 'archive', 'reason' => 'Former worker'])->assertRedirect();
        $this->assertDatabaseHas('staff_profiles', ['id' => $profile->id, 'user_id' => $portal->id, 'record_status' => 'archived']);
        $this->assertDatabaseHas('status_histories', ['subject_type' => $profile->getMorphClass(), 'subject_id' => $profile->id, 'to_status' => 'archived']);
        $this->actingAs($admin)->patch(route('staff.status', $profile), ['action' => 'reactivate'])->assertRedirect();
        $this->assertDatabaseHas('staff_profiles', ['id' => $profile->id, 'record_status' => 'active']);
    }

    public function test_client_role_is_denied_staff_records_and_staff_user_cannot_link_accounts(): void
    {
        $client = $this->userWithRole('client');
        $staff = $this->userWithRole('staff');
        $profile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $this->actingAs($client)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('staff.user-link', $profile), ['user_id' => $staff->id])->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
