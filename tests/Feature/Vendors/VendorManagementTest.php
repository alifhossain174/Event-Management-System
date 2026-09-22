<?php

namespace Tests\Feature\Vendors;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VendorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class]);
    }

    public function test_administrator_creates_vendor_without_login_with_categories_contacts_and_service_areas(): void
    {
        $admin = $this->userWithRole('administrator');
        $category = VendorCategory::query()->create(['name' => 'Catering', 'slug' => 'catering', 'is_active' => true]);
        $this->actingAs($admin)->post(route('vendors.store'), ['display_name' => '  Fresh Foods Ltd  ', 'primary_email' => 'SALES@FRESH.TEST', 'country_code' => 'bd', 'category_ids' => [$category->id]])->assertRedirect();
        $vendor = Vendor::query()->firstOrFail();
        $this->assertNull($vendor->user_id);
        $this->assertSame('Fresh Foods Ltd', $vendor->display_name);
        $this->assertSame('sales@fresh.test', $vendor->normalized_email);
        $this->assertTrue($vendor->categories->contains($category));
        $this->actingAs($admin)->post(route('vendors.contacts.store', $vendor), ['name' => 'Sales Desk', 'email' => 'desk@fresh.test', 'is_primary' => true])->assertRedirect();
        $this->actingAs($admin)->post(route('vendors.service-areas.store', $vendor), ['name' => 'Dhaka'])->assertRedirect();
        $this->assertDatabaseHas('vendor_contacts', ['vendor_id' => $vendor->id, 'name' => 'Sales Desk']);
        $this->assertDatabaseHas('vendor_service_areas', ['vendor_id' => $vendor->id, 'normalized_name' => 'dhaka']);
    }

    public function test_linked_vendor_sees_only_own_record_and_link_is_unique(): void
    {
        $admin = $this->userWithRole('administrator');
        $portal = $this->userWithRole('vendor');
        $own = Vendor::factory()->create();
        $other = Vendor::factory()->create(['display_name' => 'Other Vendor', 'normalized_name' => 'other vendor']);
        $this->actingAs($admin)->put(route('vendors.user-link', $own), ['user_id' => $portal->id])->assertRedirect();
        $this->actingAs($portal)->get(route('vendors.index'))->assertOk()->assertSee($own->display_name)->assertDontSee('Other Vendor');
        $this->actingAs($portal)->get(route('vendors.show', $own))->assertOk();
        $this->actingAs($portal)->get(route('vendors.show', $other))->assertForbidden();
        $this->actingAs($admin)->put(route('vendors.user-link', $other), ['user_id' => $portal->id])->assertSessionHasErrors('user_id');
    }

    public function test_archive_preserves_contacts_link_and_history_while_unauthorized_user_is_denied(): void
    {
        $admin = $this->userWithRole('administrator');
        $client = $this->userWithRole('client');
        $vendor = Vendor::factory()->create();
        $this->actingAs($admin)->post(route('vendors.contacts.store', $vendor), ['name' => 'Owner', 'email' => 'owner@test.test', 'is_primary' => true]);
        $this->actingAs($admin)->patch(route('vendors.status', $vendor), ['action' => 'archive', 'reason' => 'Inactive supplier'])->assertRedirect();
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'status' => 'archived']);
        $this->assertDatabaseHas('vendor_contacts', ['vendor_id' => $vendor->id, 'name' => 'Owner']);
        $this->assertDatabaseHas('status_histories', ['subject_type' => $vendor->getMorphClass(), 'subject_id' => $vendor->id, 'to_status' => 'archived']);
        $this->actingAs($client)->get(route('vendors.show', $vendor))->assertForbidden();
    }

    public function test_vendor_category_is_configurable_through_shared_master_data(): void
    {
        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->post(route('settings.master-data.store', 'vendor-categories'), ['name' => 'Photography', 'slug' => 'photography', 'description' => null, 'is_active' => 1, 'sort_order' => 0])->assertRedirect();
        $this->assertDatabaseHas('vendor_categories', ['slug' => 'photography']);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
