<?php

namespace Tests\Feature\Staff;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Role;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use App\Services\StaffOperationsService;
use Carbon\CarbonImmutable;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class StaffOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_overlapping_schedule_blocks_and_authorized_reasoned_override_is_audited(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = StaffProfile::factory()->create();
        $event = $this->event();
        $service = app(StaffOperationsService::class);
        $service->createShift($staff, $this->shiftData(), $admin);

        try {
            $service->createAssignment($event, $staff, $this->assignmentData(), $admin);
            $this->fail('An overlapping assignment should require an override.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('staff_assignments', 0);
        }

        $assignment = $service->createAssignment($event, $staff, $this->assignmentData() + [
            'override_conflict' => true, 'override_reason' => 'Only qualified operator available.',
        ], $admin);
        $this->assertTrue($assignment->conflict_overridden);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.assignment.created', 'subject_id' => $assignment->id]);
        $this->assertDatabaseHas('status_histories', ['subject_type' => $assignment->getMorphClass(), 'subject_id' => $assignment->id, 'to_status' => 'planned']);
    }

    public function test_approved_leave_affects_availability(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = StaffProfile::factory()->create();
        $leave = app(StaffOperationsService::class)->requestLeave($staff, [
            'starts_on' => '2026-12-10', 'ends_on' => '2026-12-11', 'leave_type' => 'Annual', 'reason' => 'Family commitment',
        ], $admin);
        app(StaffOperationsService::class)->reviewLeave($leave, 'approved', $admin, 'Approved');

        $this->expectException(ValidationException::class);
        app(StaffOperationsService::class)->createAssignment($this->event(), $staff, $this->assignmentData(), $admin);
    }

    public function test_manager_can_manage_staff_without_login_and_archive_preserves_history(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = StaffProfile::factory()->create(['user_id' => null]);
        $event = $this->event();
        $assignment = app(StaffOperationsService::class)->createAssignment($event, $staff, $this->assignmentData(), $admin);
        $staff->update(['record_status' => 'archived', 'archived_at' => now()]);

        $this->assertDatabaseHas('staff_assignments', ['id' => $assignment->id, 'staff_profile_id' => $staff->id]);
        $this->expectException(ValidationException::class);
        app(StaffOperationsService::class)->createAssignment($this->event('2026-12-12 10:00:00', '2026-12-12 12:00:00'), $staff, $this->assignmentData('2026-12-12 10:00:00', '2026-12-12 12:00:00'), $admin);
    }

    public function test_linked_staff_sees_own_schedule_and_can_update_own_assignment_only(): void
    {
        $admin = $this->userWithRole('administrator');
        $portal = $this->userWithRole('staff');
        $otherPortal = $this->userWithRole('staff');
        $staff = StaffProfile::factory()->create(['user_id' => $portal->id]);
        $other = StaffProfile::factory()->create(['user_id' => $otherPortal->id]);
        $event = $this->event();
        $this->enableModule($event);
        $assignment = app(StaffOperationsService::class)->createAssignment($event, $staff, $this->assignmentData(), $admin);
        $otherAssignment = StaffAssignment::factory()->create(['event_id' => $event, 'staff_profile_id' => $other, 'scheduled_starts_at' => $event->starts_at, 'scheduled_ends_at' => $event->ends_at]);
        app(StaffOperationsService::class)->transitionAssignment($assignment, 'confirmed', $admin, null, null);

        $this->actingAs($portal)->get(route('staff.operations.index'))->assertOk()->assertSee($event->reference_number);
        $this->actingAs($portal)->get(route('events.staff.index', $event))->assertOk()->assertSee($staff->display_name)->assertDontSee($other->display_name);
        $this->actingAs($portal)->patch(route('events.staff.status', [$event, $assignment]), ['status' => 'in_progress'])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('staff_assignments', ['id' => $assignment->id, 'status' => 'in_progress']);
        $this->actingAs($portal)->patch(route('events.staff.status', [$event, $otherAssignment]), ['status' => 'confirmed'])->assertForbidden();
    }

    public function test_salary_records_are_confidential_and_state_non_payroll_scope(): void
    {
        $admin = $this->userWithRole('administrator');
        $manager = $this->userWithRole('event-manager');
        $finance = $this->userWithRole('finance-accounts');
        $staff = StaffProfile::factory()->create();
        $record = app(StaffOperationsService::class)->recordSalary($staff, [
            'period_starts_on' => '2026-11-01', 'period_ends_on' => '2026-11-30', 'amount' => '1500.0000',
            'currency_code' => 'USD', 'payment_status' => 'due',
        ], $admin);

        $this->actingAs($manager)->get(route('staff.operations.index'))->assertOk()->assertDontSee('Confidential salary/payment tracking');
        $this->actingAs($finance)->get(route('staff.operations.index'))->assertOk()->assertSee('not a statutory payroll')->assertSee('1,500.00');
        $this->actingAs($finance)->patch(route('staff.salary.status', [$staff, $record]), [
            'payment_status' => 'paid', 'paid_on' => '2026-11-30', 'payment_reference' => 'CASH-11',
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('salary_records', ['id' => $record->id, 'payment_status' => 'paid', 'payment_reference' => 'CASH-11']);
        $this->assertDatabaseHas('status_histories', ['subject_type' => $record->getMorphClass(), 'subject_id' => $record->id, 'to_status' => 'paid']);
        $this->actingAs($manager)->post(route('staff.salary.store', $staff), [
            'period_starts_on' => '2026-12-01', 'period_ends_on' => '2026-12-31', 'amount' => '100', 'currency_code' => 'USD', 'payment_status' => 'due',
        ])->assertForbidden();
    }

    public function test_attendance_is_reportable_by_staff_date_and_event(): void
    {
        $manager = $this->userWithRole('event-manager');
        $staff = StaffProfile::factory()->create();
        $event = $this->event();
        $this->actingAs($manager)->post(route('staff.attendance.store', $staff), [
            'attendance_date' => '2026-12-10', 'event_id' => $event->id, 'status' => 'present',
            'clocked_in_at' => '2026-12-10 10:00:00', 'clocked_out_at' => '2026-12-10 12:00:00',
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('attendances', ['staff_profile_id' => $staff->id, 'event_id' => $event->id, 'attendance_date' => '2026-12-10', 'status' => 'present', 'recorded_by_user_id' => $manager->id]);
        $this->actingAs($manager)->get(route('staff.operations.index', ['staff' => $staff->id, 'from' => '2026-12-10', 'to' => '2026-12-10']))->assertOk()->assertSee($event->reference_number)->assertSee('Present');
    }

    public function test_manager_records_event_linked_performance_notes(): void
    {
        $manager = $this->userWithRole('event-manager');
        $staff = StaffProfile::factory()->create();
        $event = $this->event();

        $this->actingAs($manager)->post(route('staff.performance.store', $staff), [
            'event_id' => $event->id, 'period_starts_on' => '2026-12-01', 'period_ends_on' => '2026-12-31',
            'score' => '92.50', 'summary' => 'Led the operations team effectively.',
            'strengths' => 'Clear communication', 'improvement_notes' => 'Delegate earlier.',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('performance_records', [
            'staff_profile_id' => $staff->id, 'event_id' => $event->id,
            'score' => '92.50', 'reviewer_user_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.performance.recorded']);
    }

    public function test_disabled_staff_module_protects_routes_preserves_data_and_registers_detector(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $staff = StaffProfile::factory()->create();
        $assignment = StaffAssignment::factory()->create(['event_id' => $event, 'staff_profile_id' => $staff, 'scheduled_starts_at' => $event->starts_at, 'scheduled_ends_at' => $event->ends_at]);
        EventModuleSetting::query()->create(['event_id' => $event->id, 'module_key' => 'staff', 'is_enabled' => false, 'source' => 'manual']);

        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'staff'));
        $this->actingAs($admin)->get(route('events.staff.index', $event))->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('staff_assignments', ['id' => $assignment->id]);

        $event->moduleSettings()->where('module_key', 'staff')->update(['is_enabled' => true]);
        $this->actingAs($admin)->get(route('events.staff.index', $event))->assertOk();
    }

    private function event(string $starts = '2026-12-10 10:00:00', string $ends = '2026-12-10 12:00:00'): Event
    {
        return Event::factory()->create(['starts_at' => $starts, 'ends_at' => $ends, 'status' => 'planning']);
    }

    private function shiftData(): array
    {
        return ['title' => 'Operations shift', 'starts_at' => CarbonImmutable::parse('2026-12-10 09:00:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-12-10 13:00:00', 'UTC')];
    }

    private function assignmentData(string $starts = '2026-12-10 10:00:00', string $ends = '2026-12-10 12:00:00'): array
    {
        return ['role_title' => 'Coordinator', 'responsibilities' => 'Coordinate event floor.', 'scheduled_starts_at' => CarbonImmutable::parse($starts, 'UTC'), 'scheduled_ends_at' => CarbonImmutable::parse($ends, 'UTC')];
    }

    private function enableModule(Event $event): void
    {
        EventModuleSetting::query()->updateOrCreate(['event_id' => $event->id, 'module_key' => 'staff'], ['is_enabled' => true, 'source' => 'manual']);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
