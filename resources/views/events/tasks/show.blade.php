@php($localDue = $task->due_at?->setTimezone($event->timezone))
<x-layouts.app
    :title="$task->title"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name, 'url' => route('events.show', $event)],
        ['label' => 'Tasks', 'url' => route('events.tasks.index', $event)],
        ['label' => $task->title],
    ]"
>
    <x-ui.page-header :title="$task->title" :subtitle="$event->reference_number.' · '.str($task->priority)->headline().' priority'">
        <x-slot:actions><x-ui.status-badge :status="$task->status"/><a class="btn btn-outline-secondary" href="{{ route('events.tasks.index', $event) }}">All tasks</a></x-slot:actions>
    </x-ui.page-header>

    @if($task->isOverdue())<div class="alert alert-danger" role="status">This task is overdue. Overdue state is calculated from the deadline and current completion state.</div>@endif
    @if($task->archived_at)<div class="alert alert-warning" role="status">This task is archived. Its assignments, comments, attachments, and status history remain preserved.</div>@endif

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card mb-4"><div class="card-body p-4">
                <h2 class="h4">Task details</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Due</dt><dd class="col-sm-8">{{ $task->due_at?->setTimezone($timezone)->format('Y-m-d H:i T') ?? 'No deadline' }}</dd>
                    <dt class="col-sm-4">Progress</dt><dd class="col-sm-8">{{ $task->progress_percent }}%</dd>
                    <dt class="col-sm-4">Assignees</dt><dd class="col-sm-8">{{ $task->assignments->map(fn($a) => $a->displayName())->join(', ') ?: 'Unassigned' }}</dd>
                    <dt class="col-sm-4">Description</dt><dd class="col-sm-8 mb-0">{{ $task->description ?: '—' }}</dd>
                </dl>
            </div></section>

            <section class="card mb-4"><div class="card-body p-4">
                <h2 class="h4">Comments</h2>
                @forelse($task->comments as $comment)
                    <article class="border rounded p-3 mb-3"><p class="mb-2">{{ $comment->body }}</p><small class="text-secondary">{{ $comment->author?->name ?? 'Archived user' }} · {{ $comment->created_at->setTimezone($timezone)->format('Y-m-d H:i T') }}</small></article>
                @empty <x-ui.empty-state title="No comments" description="Task discussion will appear here."/> @endforelse
                @can('comment', $task)
                    <form method="POST" action="{{ route('events.tasks.comments.store', [$event, $task]) }}">@csrf<x-ui.form.textarea name="body" label="Add comment" rows="3" required/><button class="btn btn-primary" type="submit">Add comment</button></form>
                @endcan
            </div></section>

            <section class="card mb-4"><div class="card-body p-4">
                <h2 class="h4">Protected attachments</h2>
                <x-ui.data-table :columns="[['label'=>'Document'],['label'=>'Uploaded by'],['label'=>'Action']]" caption="Task attachments" :empty="$task->attachments->isEmpty()" empty-title="No attachments">
                    @foreach($task->attachments as $attachment)
                        <tr><td>{{ $attachment->document->title }}<span class="d-block small text-secondary">{{ $attachment->note ?: $attachment->document->currentVersion?->original_name }}</span></td><td>{{ $attachment->attachedBy?->name ?? 'Archived user' }}</td><td>@can('view', $attachment->document)<a class="btn btn-sm btn-outline-primary" href="{{ route('documents.show', $attachment->document) }}">Open</a>@endcan</td></tr>
                    @endforeach
                </x-ui.data-table>
                @can('attach', $task)
                    <hr><form method="POST" enctype="multipart/form-data" action="{{ route('events.tasks.attachments.store', [$event, $task]) }}">@csrf
                        <x-ui.form.input name="title" label="Document title" required/>
                        <x-ui.form.select name="document_category_id" label="Category" :options="$categories->pluck('name','id')->all()" placeholder="General / uncategorized"/>
                        <x-ui.form.input type="file" name="file" label="File" required/>
                        <x-ui.form.textarea name="note" label="Attachment note" rows="2"/>
                        <button class="btn btn-primary" type="submit">Upload securely</button>
                    </form>
                @endcan
            </div></section>

            <section class="card"><div class="card-body p-4">
                <h2 class="h4">Status history</h2>
                <x-ui.data-table :columns="[['label'=>'Change'],['label'=>'Actor'],['label'=>'When'],['label'=>'Reason']]" caption="Task status history" :empty="$task->statusHistory->isEmpty()" empty-title="No status history">
                    @foreach($task->statusHistory as $history)<tr><td>{{ $history->from_status ? str($history->from_status)->headline().' → ' : '' }}{{ str($history->to_status)->headline() }}</td><td>{{ $history->actor?->name ?? 'System' }}</td><td>{{ $history->changed_at->setTimezone($timezone)->format('Y-m-d H:i T') }}</td><td>{{ $history->reason ?: '—' }}</td></tr>@endforeach
                </x-ui.data-table>
            </div></section>
        </div>

        <aside class="col-xl-4">
            @can('assign', $task)
                <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Add assignee</h2>
                    <form method="POST" action="{{ route('events.tasks.assignments.store', [$event, $task]) }}">@csrf
                        <x-ui.form.select name="user_id" label="Manager / User" :options="$users->pluck('name','id')->all()" placeholder="Choose a User or leave blank" help="Choose exactly one User or Staff profile."/>
                        <x-ui.form.select name="staff_profile_id" label="Staff profile" :options="$staff->pluck('display_name','id')->all()" placeholder="Choose Staff or leave blank"/>
                        <button class="btn btn-outline-primary" type="submit">Add assignee</button>
                    </form>
                </div></section>
            @endcan

            @can('update', $task)
                <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Edit task</h2><form method="POST" action="{{ route('events.tasks.update', [$event, $task]) }}">@csrf @method('PUT')
                    <x-ui.form.input name="title" label="Title" :value="$task->title" required/><x-ui.form.textarea name="description" label="Description" rows="4" :value="$task->description"/><x-ui.form.input type="datetime-local" name="due_at_local" label="Due date and time" :value="$localDue?->format('Y-m-d\TH:i')"/><x-ui.form.select name="priority" label="Priority" :options="['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent']" :selected="$task->priority" required/><button class="btn btn-primary" type="submit">Save task</button>
                </form></div></section>
            @endcan

            @can('complete', $task)
                @if(!in_array($task->status, ['completed','cancelled']))
                    <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Progress and status</h2><form method="POST" action="{{ route('events.tasks.status', [$event, $task]) }}">@csrf @method('PATCH')
                        <x-ui.form.select name="status" label="New status" :options="collect(\App\Models\Task::STATUSES)->reject(fn($v) => in_array($v, ['pending', $task->status]))->mapWithKeys(fn($v)=>[$v=>str($v)->headline()])->all()" required/><x-ui.form.input type="number" min="0" max="100" name="progress_percent" label="Progress percent" :value="$task->progress_percent" required/><x-ui.form.textarea name="reason" label="Reason / completion note" rows="3"/><button class="btn btn-success" type="submit">Update status</button>
                    </form></div></section>
                @endif
            @endcan

            @can('archive', $task)
                @if(!$task->archived_at)<section class="card border-warning"><div class="card-body p-4"><h2 class="h4">Archive task</h2><form method="POST" action="{{ route('events.tasks.archive', [$event, $task]) }}">@csrf @method('PATCH')<x-ui.form.textarea name="reason" label="Archive reason" rows="3" required/><button class="btn btn-warning" type="submit">Archive with history</button></form></div></section>@endif
            @endcan
        </aside>
    </div>
</x-layouts.app>
