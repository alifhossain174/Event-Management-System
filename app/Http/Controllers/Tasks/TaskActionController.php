<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\AssignTaskRequest;
use App\Http\Requests\Tasks\TaskAttachmentRequest;
use App\Http\Requests\Tasks\TaskCommentRequest;
use App\Http\Requests\Tasks\TransitionTaskRequest;
use App\Models\Event;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class TaskActionController extends Controller
{
    public function assign(AssignTaskRequest $request, Event $event, Task $task, TaskService $service): RedirectResponse
    {
        $this->assertNested($event, $task);
        $service->assign($task, $request->validated(), $request->user());

        return back()->with('status', 'Assignee added.');
    }

    public function comment(TaskCommentRequest $request, Event $event, Task $task, TaskService $service): RedirectResponse
    {
        $this->assertNested($event, $task);
        $service->comment($task, $request->validated('body'), $request->user());

        return back()->with('status', 'Comment added.');
    }

    public function attach(TaskAttachmentRequest $request, Event $event, Task $task, TaskService $service): RedirectResponse
    {
        $this->assertNested($event, $task);
        $service->attach($task, $request->safe()->except('file'), $request->file('file'), $request->user());

        return back()->with('status', 'Protected attachment added.');
    }

    public function transition(TransitionTaskRequest $request, Event $event, Task $task, TaskService $service): RedirectResponse
    {
        $this->assertNested($event, $task);
        $data = $request->validated();
        $service->transition($task, $data['status'], (int) $data['progress_percent'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Task status updated.');
    }

    public function archive(Request $request, Event $event, Task $task, TaskService $service): RedirectResponse
    {
        $this->assertNested($event, $task);
        Gate::authorize('archive', $task);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->archive($task, $request->user(), $data['reason']);

        return redirect()->route('events.tasks.index', $event)->with('status', 'Task archived with its history preserved.');
    }

    private function assertNested(Event $event, Task $task): void
    {
        abort_unless($task->event_id === $event->getKey(), 404);
    }
}
