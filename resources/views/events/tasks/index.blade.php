<x-layouts.app
    :title="'Tasks · '.$event->name"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name, 'url' => route('events.show', $event)],
        ['label' => 'Tasks'],
    ]"
>
    <x-ui.page-header title="Event tasks" :subtitle="$event->reference_number.' · '.$event->name">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('events.show', $event) }}">Back to event</a></x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('events.tasks.index', $event)">
        <div class="col-lg-3"><x-ui.form.input name="q" label="Search" :value="$filters['q'] ?? ''"/></div>
        <div class="col-md-3 col-lg-2"><x-ui.form.select name="status" label="Status" :options="collect(\App\Models\Task::STATUSES)->mapWithKeys(fn($v) => [$v => str($v)->headline()])->all()" :selected="$filters['status'] ?? ''" placeholder="All statuses"/></div>
        <div class="col-md-3 col-lg-2"><x-ui.form.select name="priority" label="Priority" :options="collect(\App\Models\Task::PRIORITIES)->mapWithKeys(fn($v) => [$v => str($v)->headline()])->all()" :selected="$filters['priority'] ?? ''" placeholder="All priorities"/></div>
        <div class="col-md-3 col-lg-2"><x-ui.form.select name="deadline" label="Deadline" :options="['overdue'=>'Overdue','today'=>'Due today','upcoming'=>'Upcoming','none'=>'No deadline']" :selected="$filters['deadline'] ?? ''" placeholder="Any deadline"/></div>
        <div class="col-md-3 col-lg-3"><x-ui.form.select name="assignee" label="Assignee" :options="$users->mapWithKeys(fn($u) => ['user:'.$u->id => 'User · '.$u->name])->merge($staff->mapWithKeys(fn($s) => ['staff:'.$s->id => 'Staff · '.$s->display_name]))->all()" :selected="$filters['assignee'] ?? ''" placeholder="Anyone"/></div>
    </x-ui.filter-bar>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card"><div class="card-body p-4">
                <x-ui.data-table :columns="[['label'=>'Task'],['label'=>'Due'],['label'=>'Assignees'],['label'=>'Status'],['label'=>'Progress']]" caption="Event tasks" :empty="$tasks->isEmpty()" empty-title="No tasks match these filters">
                    @foreach($tasks as $task)
                        <tr>
                            <td><a class="fw-semibold" href="{{ route('events.tasks.show', [$event, $task]) }}">{{ $task->title }}</a><span class="d-block small text-secondary">{{ str($task->priority)->headline() }} priority</span></td>
                            <td>@if($task->due_at)<span class="{{ $task->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $task->due_at->setTimezone($timezone)->format('Y-m-d H:i T') }}</span>@if($task->isOverdue())<span class="badge text-bg-danger ms-1">Overdue</span>@endif @else — @endif</td>
                            <td>{{ $task->assignments->map(fn($a) => $a->displayName())->join(', ') ?: 'Unassigned' }}</td>
                            <td><x-ui.status-badge :status="$task->status"/></td>
                            <td>{{ $task->progress_percent }}%</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
                <x-ui.pagination :paginator="$tasks"/>
            </div></section>
        </div>

        <aside class="col-xl-4">
            @can('create', [\App\Models\Task::class, $event])
                <section class="card"><div class="card-body p-4">
                    <h2 class="h4">Create task</h2>
                    <form method="POST" action="{{ route('events.tasks.store', $event) }}">
                        @csrf
                        <x-ui.form.input name="title" label="Title" required/>
                        <x-ui.form.textarea name="description" label="Description" rows="4"/>
                        <x-ui.form.input type="datetime-local" name="due_at_local" label="Due date and time"/>
                        <x-ui.form.select name="priority" label="Priority" :options="['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent']" selected="normal" required/>
                        <button class="btn btn-primary" type="submit">Create task</button>
                    </form>
                </div></section>
            @endcan
        </aside>
    </div>
</x-layouts.app>
