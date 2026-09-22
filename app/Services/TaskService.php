<?php

namespace App\Services;

use App\Models\DocumentCategory;
use App\Models\Event;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskStatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TaskService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly DocumentService $documents,
        private readonly BranchScope $branches,
    ) {}

    public function create(Event $event, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $task = $event->tasks()->create($data + [
                'status' => 'pending', 'progress_percent' => 0,
                'created_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->recordStatus($task, null, 'pending', $actor, 'Task created');
            $this->audit->record('task.created', $task, [], $this->snapshot($task), $actor);

            return $task;
        }, 3);
    }

    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->getKey());
            $before = $this->snapshot($task);
            $task->update($data + ['updated_by_user_id' => $actor->getKey()]);
            $this->audit->record('task.updated', $task, $before, $this->snapshot($task), $actor);

            return $task->refresh();
        }, 3);
    }

    public function assign(Task $task, array $data, User $actor): TaskAssignment
    {
        $hasUser = filled($data['user_id'] ?? null);
        $hasStaff = filled($data['staff_profile_id'] ?? null);
        if ($hasUser === $hasStaff) {
            throw ValidationException::withMessages(['assignee' => 'Choose exactly one manager/User or Staff profile.']);
        }

        if ($hasUser) {
            $assignee = User::query()->where('is_active', true)->whereNull('deleted_at')->findOrFail($data['user_id']);
            $branchId = null;
        } else {
            $staff = StaffProfile::query()->findOrFail($data['staff_profile_id']);
            if ($staff->record_status !== 'active' || $staff->employment_status === 'separated') {
                throw ValidationException::withMessages(['staff_profile_id' => 'Archived or separated Staff cannot receive new tasks.']);
            }
            $assignee = $staff;
            $branchId = $staff->branch_id;
        }

        if (! $this->branches->permits($actor, $branchId)) {
            throw new AuthorizationException('You cannot assign work outside your branch access.');
        }

        return DB::transaction(function () use ($task, $actor, $hasUser, $assignee) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->getKey());
            $assignment = TaskAssignment::query()->firstOrCreate([
                'task_id' => $task->getKey(),
                'user_id' => $hasUser ? $assignee->getKey() : null,
                'staff_profile_id' => $hasUser ? null : $assignee->getKey(),
            ], ['assigned_by_user_id' => $actor->getKey(), 'assigned_at' => now()]);
            if ($assignment->wasRecentlyCreated) {
                $this->audit->record('task.assigned', $task, [], [
                    'task_assignment_id' => $assignment->getKey(),
                    'assignee_type' => $hasUser ? 'user' : 'staff', 'assignee_id' => $assignee->getKey(),
                ], $actor);
            }

            return $assignment->load(['user', 'staff']);
        }, 3);
    }

    public function comment(Task $task, string $body, User $actor): TaskComment
    {
        return DB::transaction(function () use ($task, $body, $actor) {
            $comment = $task->comments()->create(['body' => $body, 'author_user_id' => $actor->getKey()]);
            $this->audit->record('task.comment_added', $task, [], ['comment_id' => $comment->getKey()], $actor);

            return $comment;
        });
    }

    public function attach(Task $task, array $data, UploadedFile $file, User $actor): TaskAttachment
    {
        return DB::transaction(function () use ($task, $data, $file, $actor) {
            $document = $this->documents->create([
                'title' => $data['title'], 'description' => $data['note'] ?? null,
                'document_category_id' => $data['document_category_id'] ?? DocumentCategory::query()->where('name', 'General')->value('id'),
                'version_notes' => $data['version_notes'] ?? 'Initial task attachment',
                'branch_id' => $task->event->branch_id,
            ], $file, [$task], $actor);
            $attachment = $task->attachments()->create([
                'document_id' => $document->getKey(), 'attached_by_user_id' => $actor->getKey(), 'note' => $data['note'] ?? null,
            ]);
            $this->audit->record('task.attachment_added', $task, [], ['attachment_id' => $attachment->getKey(), 'document_id' => $document->getKey()], $actor);

            return $attachment->load('document.currentVersion');
        });
    }

    public function transition(Task $task, string $to, int $progress, User $actor, ?string $reason): Task
    {
        return DB::transaction(function () use ($task, $to, $progress, $actor, $reason) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->getKey());
            $allowed = [
                'pending' => ['in_progress', 'blocked', 'completed', 'cancelled'],
                'in_progress' => ['blocked', 'completed', 'cancelled'],
                'blocked' => ['in_progress', 'completed', 'cancelled'],
                'completed' => [], 'cancelled' => [],
            ];
            if (! in_array($to, $allowed[$task->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Task cannot move from {$task->status} to {$to}."]);
            }
            if ($to === 'blocked' && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required when blocking a task.']);
            }
            $from = $task->status;
            $task->update([
                'status' => $to,
                'progress_percent' => $to === 'completed' ? 100 : $progress,
                'completed_at' => $to === 'completed' ? now() : null,
                'completed_by_user_id' => $to === 'completed' ? $actor->getKey() : null,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->recordStatus($task, $from, $to, $actor, $reason);
            $this->audit->record('task.status_changed', $task, ['status' => $from], ['status' => $to, 'progress_percent' => $task->progress_percent, 'reason' => $reason], $actor);

            return $task->refresh();
        }, 3);
    }

    public function archive(Task $task, User $actor, string $reason): Task
    {
        return DB::transaction(function () use ($task, $actor, $reason) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->getKey());
            if ($task->archived_at) {
                throw new DomainException('Task is already archived.');
            }
            $task->update(['archived_at' => now(), 'archived_by_user_id' => $actor->getKey(), 'archive_reason' => $reason]);
            $this->audit->record('task.archived', $task, [], ['reason' => $reason], $actor);

            return $task->refresh();
        });
    }

    private function recordStatus(Task $task, ?string $from, string $to, User $actor, ?string $reason): void
    {
        TaskStatusHistory::query()->create([
            'task_id' => $task->getKey(), 'from_status' => $from, 'to_status' => $to,
            'actor_type' => 'user', 'actor_user_id' => $actor->getKey(), 'changed_at' => now(),
            'reason' => $reason,
        ]);
    }

    private function snapshot(Task $task): array
    {
        return [
            'event_id' => $task->event_id, 'title' => $task->title,
            'due_at' => $task->due_at?->toIso8601String(), 'priority' => $task->priority,
            'status' => $task->status, 'progress_percent' => $task->progress_percent,
        ];
    }
}
