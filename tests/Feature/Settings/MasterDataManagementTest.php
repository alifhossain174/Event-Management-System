<?php

namespace Tests\Feature\Settings;

use App\Models\EventCategory;
use App\Models\FinanceCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_manage_separate_category_domains_with_shared_screens(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->post(route('settings.master-data.store', 'event-categories'), [
            'name' => 'Corporate Event',
            'slug' => '',
            'description' => 'Corporate and seminar events.',
            'sort_order' => 10,
            'is_active' => '1',
        ])->assertRedirect(route('settings.master-data.index', 'event-categories'));

        $eventCategory = EventCategory::query()->firstOrFail();
        $this->assertSame('corporate-event', $eventCategory->slug);

        $this->actingAs($administrator)->post(route('settings.master-data.store', 'finance-categories'), [
            'name' => 'Venue expense',
            'slug' => 'venue-expense',
            'direction' => 'expense',
            'sort_order' => 20,
            'is_active' => '1',
        ])->assertRedirect(route('settings.master-data.index', 'finance-categories'));

        $this->assertSame('expense', FinanceCategory::query()->firstOrFail()->direction);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'master-data.event-categories.created',
            'subject_id' => $eventCategory->id,
        ]);
    }

    public function test_category_lists_are_filterable_paginated_and_archived_non_destructively(): void
    {
        $administrator = $this->administrator();
        EventCategory::query()->create([
            'name' => 'Unique Wedding',
            'slug' => 'unique-wedding',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        EventCategory::factory()->count(16)->create();

        $this->actingAs($administrator)
            ->get(route('settings.master-data.index', ['type' => 'event-categories', 'q' => 'Unique Wedding']))
            ->assertOk()
            ->assertSee('Unique Wedding');
        $this->actingAs($administrator)
            ->get(route('settings.master-data.index', 'event-categories'))
            ->assertOk()
            ->assertSee('pagination');

        $category = EventCategory::query()->where('slug', 'unique-wedding')->firstOrFail();
        $this->actingAs($administrator)
            ->delete(route('settings.master-data.destroy', ['event-categories', $category->id]))
            ->assertRedirect(route('settings.master-data.index', 'event-categories'));

        $this->assertSoftDeleted($category);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'master-data.event-categories.archived',
            'subject_id' => $category->id,
        ]);
    }

    public function test_unknown_master_data_type_returns_not_found(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('settings.master-data.index', 'unsupported'))
            ->assertNotFound();
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
