<?php

namespace Tests\Feature\Vendors;

use App\Contracts\ApprovedVendorCostProvider;
use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorAssignment;
use App\Models\VendorCategory;
use App\Services\EventModuleDataRegistry;
use App\Services\VendorAssignmentService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class VendorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_overlapping_commitments_warn_by_default_and_can_be_configured_to_block(): void
    {
        $admin = $this->userWithRole('administrator');
        [$vendor, $category] = $this->vendorWithCategory();
        $firstEvent = $this->event('2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $secondEvent = $this->event('2026-12-10 11:00:00', '2026-12-10 13:00:00');
        $service = app(VendorAssignmentService::class);
        $service->create($firstEvent, $this->assignmentData($vendor, $category, $firstEvent), $admin);

        $warning = $service->create($secondEvent, $this->assignmentData($vendor, $category, $secondEvent), $admin);
        $this->assertTrue($warning->availability_warning);
        $this->assertStringContainsString($firstEvent->reference_number, implode(' ', $warning->availability_warning_details));

        $vendor->update(['availability_conflict_policy' => 'block']);
        $thirdEvent = $this->event('2026-12-10 11:30:00', '2026-12-10 12:30:00');
        $this->expectException(ValidationException::class);
        $service->create($thirdEvent, $this->assignmentData($vendor, $category, $thirdEvent), $admin);
    }

    public function test_status_work_order_and_rating_history_are_audited_and_rating_requires_completion(): void
    {
        $admin = $this->userWithRole('administrator');
        [$vendor, $category] = $this->vendorWithCategory();
        $event = $this->event();
        $service = app(VendorAssignmentService::class);
        $assignment = $service->create($event, $this->assignmentData($vendor, $category, $event) + ['approved_cost' => '2500.0000'], $admin);

        try {
            $service->rate($assignment, ['score' => 5], $admin);
            $this->fail('An incomplete assignment should not be rated.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('vendor_ratings', 0);
        }

        $workOrder = $service->createWorkOrder($assignment, ['title' => 'Stage delivery', 'instructions' => 'Deliver and assemble stage.'], $admin);
        $service->transitionWorkOrder($workOrder, 'issued', $admin, 'Sent to vendor');
        $service->transition($assignment, 'approved', $admin, 'Cost approved', null);
        $service->transition($assignment, 'in_progress', $admin, null, null);
        $service->transition($assignment, 'completed', $admin, null, 'Delivered and inspected.');
        $rating = $service->rate($assignment, ['score' => 5, 'comments' => 'Excellent delivery.'], $admin);

        $this->assertSame(5, $rating->score);
        $this->assertDatabaseHas('status_histories', ['subject_type' => $assignment->getMorphClass(), 'subject_id' => $assignment->id, 'to_status' => 'completed']);
        $this->assertDatabaseHas('status_histories', ['subject_type' => $workOrder->getMorphClass(), 'subject_id' => $workOrder->id, 'to_status' => 'issued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vendor.assignment.rated', 'subject_id' => $rating->id]);
    }

    public function test_linked_vendor_portal_is_isolated_to_its_own_assignments_and_can_update_delivery(): void
    {
        $vendorUser = $this->userWithRole('vendor');
        $otherUser = $this->userWithRole('vendor');
        [$vendor, $category] = $this->vendorWithCategory(['user_id' => $vendorUser->id]);
        [$otherVendor] = $this->vendorWithCategory(['user_id' => $otherUser->id]);
        $event = $this->event();
        $this->enableModule($event);
        $assignment = VendorAssignment::factory()->create([
            'event_id' => $event->id, 'vendor_id' => $vendor->id, 'vendor_category_id' => $category->id,
            'scheduled_starts_at' => $event->starts_at, 'scheduled_ends_at' => $event->ends_at,
        ]);

        $this->actingAs($vendorUser)->get(route('events.vendors.show', [$event, $assignment]))
            ->assertOk()->assertSee($vendor->display_name)->assertDontSee($otherVendor->display_name);
        $this->actingAs($vendorUser)->patch(route('events.vendors.delivery', [$event, $assignment]), [
            'delivery_status' => 'in_progress', 'delivery_notes' => 'Crew is en route.',
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('vendor_assignments', ['id' => $assignment->id, 'delivery_status' => 'in_progress']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vendor.assignment.delivery_updated', 'subject_id' => $assignment->id]);

        $this->actingAs($otherUser)->get(route('events.vendors.show', [$event, $assignment]))->assertForbidden();
    }

    public function test_archived_vendor_cannot_receive_new_assignment_but_history_remains_visible(): void
    {
        $admin = $this->userWithRole('administrator');
        [$vendor, $category] = $this->vendorWithCategory();
        $event = $this->event();
        $assignment = app(VendorAssignmentService::class)->create($event, $this->assignmentData($vendor, $category, $event), $admin);
        $vendor->update(['status' => 'archived', 'archived_at' => now()]);

        $this->enableModule($event);
        $this->actingAs($admin)->get(route('events.vendors.show', [$event, $assignment]))->assertOk();
        $this->expectException(ValidationException::class);
        app(VendorAssignmentService::class)->create($this->event(), $this->assignmentData($vendor, $category, $event), $admin);
    }

    public function test_vendor_invoice_is_protected_and_does_not_create_an_expense(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('administrator');
        $outsider = $this->userWithRole('vendor');
        [$vendor, $category] = $this->vendorWithCategory();
        $event = $this->event();
        $this->enableModule($event);
        $assignment = app(VendorAssignmentService::class)->create($event, $this->assignmentData($vendor, $category, $event), $admin);

        $this->actingAs($admin)->post(route('events.vendors.invoices.store', [$event, $assignment]), [
            'title' => 'Supplier invoice INV-42', 'invoice_number' => 'INV-42',
            'invoice_date' => '2026-12-11', 'amount' => '975.50', 'currency_code' => 'USD',
            'file' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
        ])->assertSessionDoesntHaveErrors();

        $invoice = $assignment->invoices()->with('document.currentVersion')->firstOrFail();
        Storage::disk('local')->assertExists($invoice->document->currentVersion->path);
        $this->actingAs($outsider)->get(route('documents.show', $invoice->document))->assertForbidden();
        $this->assertFalse(Schema::hasTable('expenses'));
    }

    public function test_disabled_vendor_module_protects_routes_preserves_data_and_registers_detector(): void
    {
        $admin = $this->userWithRole('administrator');
        [$vendor, $category] = $this->vendorWithCategory();
        $event = $this->event();
        $assignment = VendorAssignment::factory()->create([
            'event_id' => $event->id, 'vendor_id' => $vendor->id, 'vendor_category_id' => $category->id,
            'scheduled_starts_at' => $event->starts_at, 'scheduled_ends_at' => $event->ends_at,
        ]);
        EventModuleSetting::query()->create(['event_id' => $event->id, 'module_key' => 'vendors', 'is_enabled' => false, 'source' => 'manual']);

        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'vendors'));
        $this->actingAs($admin)->get(route('events.vendors.show', [$event, $assignment]))
            ->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('vendor_assignments', ['id' => $assignment->id]);

        $event->moduleSettings()->where('module_key', 'vendors')->update(['is_enabled' => true]);
        $this->actingAs($admin)->get(route('events.vendors.show', [$event, $assignment]))->assertOk();
    }

    public function test_approved_costs_are_exposed_by_contract_without_creating_finance_records(): void
    {
        $admin = $this->userWithRole('administrator');
        [$vendor, $category] = $this->vendorWithCategory();
        $event = $this->event();
        $assignment = app(VendorAssignmentService::class)->create($event, $this->assignmentData($vendor, $category, $event) + ['approved_cost' => '1200.0000'], $admin);
        app(VendorAssignmentService::class)->transition($assignment, 'approved', $admin, 'Budget approved', null);

        $costs = app(ApprovedVendorCostProvider::class)->forEvent($event);
        $this->assertCount(1, $costs);
        $this->assertSame('1200.0000', $costs->first()->amount);
        $this->assertSame($assignment->id, $costs->first()->sourceId);
    }

    private function event(string $starts = '2026-12-10 10:00:00', string $ends = '2026-12-10 12:00:00'): Event
    {
        return Event::factory()->create(['starts_at' => $starts, 'ends_at' => $ends, 'status' => 'planning']);
    }

    private function enableModule(Event $event): void
    {
        EventModuleSetting::query()->updateOrCreate(
            ['event_id' => $event->id, 'module_key' => 'vendors'],
            ['is_enabled' => true, 'source' => 'manual'],
        );
    }

    /** @return array{Vendor, VendorCategory} */
    private function vendorWithCategory(array $vendorAttributes = []): array
    {
        $vendor = Vendor::factory()->create($vendorAttributes);
        $category = VendorCategory::query()->create([
            'name' => fake()->unique()->words(2, true), 'slug' => fake()->unique()->slug(),
            'is_active' => true, 'sort_order' => 0,
        ]);
        $vendor->categories()->attach($category);

        return [$vendor, $category];
    }

    /** @return array<string, mixed> */
    private function assignmentData(Vendor $vendor, VendorCategory $category, Event $event): array
    {
        return [
            'vendor_id' => $vendor->id, 'vendor_category_id' => $category->id,
            'scope' => 'Provide staging and production services.',
            'scheduled_starts_at' => $event->starts_at, 'scheduled_ends_at' => $event->ends_at,
            'quoted_cost' => '1500.0000', 'currency_code' => 'USD', 'delivery_status' => 'pending',
        ];
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
