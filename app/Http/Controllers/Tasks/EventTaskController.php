<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\DocumentCategory;
use App\Models\Event;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\User;
use App\Services\BranchScope;
use App\Services\SettingsService;
use App\Services\TaskAccessService;
use App\Services\TaskService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class EventTaskController extends Controller
{
    public function index(Request $request, Event $event, TaskAccessService $access, BranchScope $branches, SettingsService $settings): View
    {
        Gate::authorize('viewModule', [$event, 'tasks']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Task::STATUSES)],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'assignee' => ['nullable', 'string', 'max:80'],
            'deadline' => ['nullable', Rule::in(['overdue', 'today', 'upcoming', 'none'])],
            'archived' => ['nullable', 'boolean'],
        ]);
        $query = $access->visibleTo($request->user())->where('event_id', $event->getKey())
            ->with(['assignments.user', 'assignments.staff']);
        $query->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assignee'] ?? null, function (Builder $query, string $assignee) {
                [$type, $id] = array_pad(explode(':', $assignee, 2), 2, null);
                if ($type === 'user' && ctype_digit((string) $id)) {
                    $query->whereHas('assignments', fn (Builder $a) => $a->where('user_id', (int) $id));
                }
                if ($type === 'staff' && ctype_digit((string) $id)) {
                    $query->whereHas('assignments', fn (Builder $a) => $a->where('staff_profile_id', (int) $id));
                }
            })
            ->when(! ($filters['archived'] ?? false), fn (Builder $query) => $query->whereNull('archived_at'));
        $timezone = $settings->string('general.timezone');
        $todayStart = now($timezone)->startOfDay()->utc();
        $todayEnd = now($timezone)->addDay()->startOfDay()->utc();
        match ($filters['deadline'] ?? null) {
            'overdue' => $query->whereNotNull('due_at')->where('due_at', '<', now())->whereNotIn('status', ['completed', 'cancelled']),
            'today' => $query->where('due_at', '>=', $todayStart)->where('due_at', '<', $todayEnd),
            'upcoming' => $query->where('due_at', '>=', $todayEnd),
            'none' => $query->whereNull('due_at'),
            default => null,
        };
        $tasks = $query->orderByRaw('due_at IS NULL')->orderBy('due_at')->orderBy('priority')->paginate(20)->withQueryString();
        $staff = $branches->apply(StaffProfile::query()->where('record_status', 'active')->where('employment_status', '!=', 'separated'), $request->user())->orderBy('display_name')->get();
        $users = User::query()->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get();

        return view('events.tasks.index', compact('event', 'tasks', 'filters', 'staff', 'users', 'timezone'));
    }

    public function store(StoreTaskRequest $request, Event $event, TaskService $service): RedirectResponse
    {
        $task = $service->create($event, $request->taskAttributes(), $request->user());

        return redirect()->route('events.tasks.show', [$event, $task])->with('status', 'Task created.');
    }

    public function show(Request $request, Event $event, Task $task, SettingsService $settings, BranchScope $branches): View
    {
        $this->assertNested($event, $task);
        Gate::authorize('view', $task);
        $task->load(['assignments.user', 'assignments.staff', 'comments.author', 'attachments.document.currentVersion', 'attachments.attachedBy', 'statusHistory.actor']);

        return view('events.tasks.show', [
            'event' => $event, 'task' => $task,
            'categories' => DocumentCategory::query()->whereNull('deleted_at')->orderBy('name')->get(),
            'timezone' => $settings->string('general.timezone'),
            'staff' => $request->user()->can('assign', $task)
                ? $branches->apply(StaffProfile::query()->where('record_status', 'active')->where('employment_status', '!=', 'separated'), $request->user())->orderBy('display_name')->get()
                : collect(),
            'users' => $request->user()->can('assign', $task)
                ? User::query()->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Event $event, Task $task, TaskService $service): RedirectResponse
    {
        $this->assertNested($event, $task);
        $service->update($task, $request->taskAttributes(), $request->user());

        return back()->with('status', 'Task updated.');
    }

    private function assertNested(Event $event, Task $task): void
    {
        abort_unless($task->event_id === $event->getKey(), 404);
    }
}
