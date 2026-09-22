<x-layouts.app :title="'Staff · '.$event->name" wide :breadcrumbs="[['label'=>'Events','url'=>route('events.index')],['label'=>$event->name,'url'=>route('events.show',$event)],['label'=>'Staff']]">
    <x-ui.page-header title="Event staff assignments" :subtitle="$event->reference_number.' · '.$event->name">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('events.show',$event) }}">Back to event</a></x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('events.staff.index',$event)">
        <div class="col-md-4"><x-ui.form.input name="q" label="Search" :value="$filters['q']??''"/></div>
        <div class="col-md-3"><x-ui.form.select name="status" label="Status" :options="collect(['planned','confirmed','in_progress','completed','cancelled'])->mapWithKeys(fn($v)=>[$v=>str($v)->headline()])" :value="$filters['status']??''" placeholder="All statuses"/></div>
        <div class="col-md-3"><x-ui.form.select name="staff" label="Staff" :options="$staffOptions->pluck('display_name','id')" :value="$filters['staff']??''" placeholder="All staff"/></div>
    </x-ui.filter-bar>

    <section class="card mb-4"><div class="card-body p-4">
        <x-ui.data-table :columns="[['label'=>'Staff'],['label'=>'Role'],['label'=>'Schedule'],['label'=>'Status'],['label'=>'Actions']]" caption="Event staff assignments" :empty="$assignments->isEmpty()" empty-title="No staff assigned">
            @foreach($assignments as $assignment)
                <tr>
                    <td><a href="{{ route('staff.show',$assignment->staff) }}">{{ $assignment->staff->display_name }}</a><span class="d-block small text-secondary">{{ $assignment->staff->department?->name ?? 'No department' }}</span></td>
                    <td>{{ $assignment->role_title }}<span class="d-block small text-secondary">{{ str($assignment->responsibilities)->limit(80) }}</span></td>
                    <td>{{ $assignment->scheduled_starts_at->setTimezone($event->timezone)->format('Y-m-d H:i') }}<span class="d-block small text-secondary">to {{ $assignment->scheduled_ends_at->setTimezone($event->timezone)->format('Y-m-d H:i T') }}</span></td>
                    <td><x-ui.status-badge :status="$assignment->status"/> @if($assignment->conflict_overridden)<span class="badge text-bg-warning">Override</span>@endif</td>
                    <td>
                        @can('update',$assignment)
                            <form method="POST" action="{{ route('events.staff.status',[$event,$assignment]) }}" class="d-flex flex-column gap-2">@csrf @method('PATCH')
                                <label class="visually-hidden" for="assignment-status-{{ $assignment->id }}">New status</label>
                                <select class="form-select form-select-sm" id="assignment-status-{{ $assignment->id }}" name="status" required>
                                    @foreach($assignment->allowedStatusTransitions()[$assignment->status] ?? [] as $status)<option value="{{ $status }}">{{ str($status)->headline() }}</option>@endforeach
                                </select>
                                <input class="form-control form-control-sm" name="completion_notes" placeholder="Completion notes when completing">
                                <button class="btn btn-sm btn-outline-primary" @disabled(($assignment->allowedStatusTransitions()[$assignment->status] ?? [])===[])>Update</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-ui.data-table>
        {{ $assignments->links() }}
    </div></section>

    @can('create', App\Models\StaffAssignment::class)
        <section class="card"><div class="card-body p-4">
            <h2 class="h4">Assign staff</h2>
            <p class="text-secondary">Schedules are checked against active shifts, Event assignments, and approved leave. An archived or separated Staff profile cannot receive new work.</p>
            <form method="POST" action="{{ route('events.staff.store',$event) }}">@csrf
                <div class="row g-3">
                    <div class="col-md-6"><x-ui.form.select name="staff_profile_id" label="Staff" :options="$staffOptions->pluck('display_name','id')" required/></div>
                    <div class="col-md-6"><x-ui.form.input name="role_title" label="Event role" required/></div>
                    <div class="col-12"><x-ui.form.textarea name="responsibilities" label="Responsibilities" rows="3"/></div>
                    <div class="col-md-6"><x-ui.form.input name="scheduled_starts_at_local" label="Scheduled start" type="datetime-local" :value="$event->starts_at->setTimezone($event->timezone)->format('Y-m-d\TH:i')" required/></div>
                    <div class="col-md-6"><x-ui.form.input name="scheduled_ends_at_local" label="Scheduled end" type="datetime-local" :value="$event->ends_at->setTimezone($event->timezone)->format('Y-m-d\TH:i')" required/></div>
                    <div class="col-md-6"><x-ui.form.select name="responsible_manager_user_id" label="Responsible manager" :options="$managers->pluck('name','id')" placeholder="Unassigned"/></div>
                    @can('staff.override-conflicts')
                        <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="override_conflict" value="1" id="override_conflict"><label class="form-check-label" for="override_conflict">Override a detected conflict</label></div></div>
                        <div class="col-12"><x-ui.form.textarea name="override_reason" label="Conflict override reason" rows="2"/></div>
                    @endcan
                </div>
                <button class="btn btn-primary" type="submit">Create assignment</button>
            </form>
        </div></section>
    @endcan
</x-layouts.app>
