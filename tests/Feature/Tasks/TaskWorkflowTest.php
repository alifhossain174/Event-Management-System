<?php

namespace Tests\Feature\Tasks;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use App\Services\TaskService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_manager_creates_filters_and_assigns_tasks_to_users_or_staff_without_logins(): void
    {
        $manager = $this->userWithRole('event-manager');
        $event = $this->event();
        $staff = StaffProfile::factory()->create(['user_id' => null]);

        $response = $this->actingAs($manager)->post(route('events.tasks.store', $event), [
            'title' => 'Confirm stage layout', 'description' => 'Review final dimensions.',
            'due_at_local' => '2026-12-10T09:00', 'priority' => 'high',
        ]);
        $task = Task::query()->firstOrFail();
        $response->assertRedirect(route('events.tasks.show', [$event, $task]));

        $this->actingAs($manager)->post(route('events.tasks.assignments.store', [$event, $task]), ['staff_profile_id' => $staff->id])->assertSessionDoesntHaveErrors();
        $this->actingAs($manager)->post(route('events.tasks.comments.store', [$event, $task]), ['body' => 'Layout reviewed with operations.'])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('task_assignments', ['task_id' => $task->id, 'staff_profile_id' => $staff->id, 'user_id' => null]);
        $this->assertDatabaseHas('task_comments', ['task_id' => $task->id, 'author_user_id' => $manager->id, 'body' => 'Layout reviewed with operations.']);
        $this->actingAs($manager)->get(route('events.tasks.index', [$event, 'priority' => 'high', 'assignee' => 'staff:'.$staff->id]))
            ->assertOk()->assertSee('Confirm stage layout');
        $this->assertDatabaseHas('task_status_histories', ['task_id' => $task->id, 'from_status' => null, 'to_status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'task.assigned', 'subject_id' => $task->id]);
    }

    public function test_linked_staff_sees_only_assigned_task_and_can_complete_with_actor_trace(): void
    {
        $admin = $this->userWithRole('administrator');
        $staffUser = $this->userWithRole('staff');
        $otherUser = $this->userWithRole('staff');
        $staff = StaffProfile::factory()->create(['user_id' => $staffUser->id]);
        $event = $this->event();
        $task = app(TaskService::class)->create($event, ['title' => 'Open registration desk', 'priority' => 'normal', 'due_at' => now()->addDay()], $admin);
        app(TaskService::class)->assign($task, ['staff_profile_id' => $staff->id], $admin);

        $this->actingAs($staffUser)->get(route('events.tasks.show', [$event, $task]))->assertOk()->assertSee('Open registration desk');
        $this->actingAs($otherUser)->get(route('events.tasks.show', [$event, $task]))->assertForbidden();
        $this->actingAs($staffUser)->patch(route('events.tasks.status', [$event, $task]), [
            'status' => 'completed', 'progress_percent' => 50, 'reason' => 'Desk opened and handed over.',
        ])->assertSessionDoesntHaveErrors();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(100, $task->progress_percent);
        $this->assertSame($staffUser->id, $task->completed_by_user_id);
        $this->assertNotNull($task->completed_at);
        $this->assertDatabaseHas('task_status_histories', ['task_id' => $task->id, 'from_status' => 'pending', 'to_status' => 'completed', 'actor_user_id' => $staffUser->id]);
    }

    public function test_overdue_is_derived_and_completed_tasks_stop_being_overdue(): void
    {
        $admin = $this->userWithRole('administrator');
        $task = app(TaskService::class)->create($this->event(), ['title' => 'Late task', 'priority' => 'urgent', 'due_at' => now()->subMinute()], $admin);
        $this->assertTrue($task->isOverdue());
        $this->assertFalse(Schema::hasColumn('event_tasks', 'is_overdue'));

        app(TaskService::class)->transition($task, 'completed', 0, $admin, 'Finished');
        $this->assertFalse($task->refresh()->isOverdue());
    }

    public function test_protected_attachment_is_available_to_assignee_but_not_unassigned_staff(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('administrator');
        $staffUser = $this->userWithRole('staff');
        $otherUser = $this->userWithRole('staff');
        $staff = StaffProfile::factory()->create(['user_id' => $staffUser->id]);
        $event = $this->event();
        $task = app(TaskService::class)->create($event, ['title' => 'Read briefing', 'priority' => 'normal'], $admin);
        app(TaskService::class)->assign($task, ['staff_profile_id' => $staff->id], $admin);

        $this->actingAs($admin)->post(route('events.tasks.attachments.store', [$event, $task]), [
            'title' => 'Operations brief', 'file' => UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\nbrief\n%%EOF"),
        ])->assertSessionDoesntHaveErrors();
        $document = $task->attachments()->firstOrFail()->document;

        $this->actingAs($staffUser)->get(route('documents.download', $document))->assertOk();
        $this->actingAs($otherUser)->get(route('documents.download', $document))->assertForbidden();
    }

    public function test_disabled_task_module_hides_routes_and_detector_preserves_records(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $task = Task::factory()->create(['event_id' => $event]);
        $event->moduleSettings()->where('module_key', 'tasks')->update(['is_enabled' => false]);

        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'tasks'));
        $this->actingAs($admin)->get(route('events.tasks.show', [$event, $task]))->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('event_tasks', ['id' => $task->id]);

        $event->moduleSettings()->where('module_key', 'tasks')->update(['is_enabled' => true]);
        $this->actingAs($admin)->get(route('events.tasks.show', [$event, $task]))->assertOk();
    }

    private function event(): Event
    {
        $event = Event::factory()->create(['starts_at' => '2026-12-10 10:00:00', 'ends_at' => '2026-12-10 18:00:00', 'timezone' => 'UTC', 'status' => 'planning']);
        EventModuleSetting::query()->updateOrCreate(['event_id' => $event->id, 'module_key' => 'tasks'], ['is_enabled' => true, 'source' => 'manual']);

        return $event;
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
