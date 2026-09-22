@php
    $organizationStart = $event->starts_at->setTimezone($organizationTimezone);
    $organizationEnd = $event->ends_at->setTimezone($organizationTimezone);
    $eventStart = $event->starts_at->setTimezone($event->timezone);
    $eventEnd = $event->ends_at->setTimezone($event->timezone);
@endphp

<x-layouts.app
    :title="$event->name"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name],
    ]"
>
    <x-ui.page-header :title="$event->name" :subtitle="$event->reference_number.' · '.$event->category->name">
        <x-slot:actions>
            <x-ui.status-badge :status="$event->status"/>
            @can('update', $event)<a class="btn btn-outline-primary" href="{{ route('events.edit', $event) }}">Edit event</a>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($event->archived_at)
        <div class="alert alert-warning" role="status">This Event was archived on {{ $event->archived_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}. Its history remains intact.</div>
    @elseif ($event->status === 'completed')
        <div class="alert alert-info" role="status">Completed Events are operationally read-only. An authorized administrator may reopen this Event for a documented correction.</div>
    @elseif ($event->status === 'cancelled')
        <div class="alert alert-warning" role="status"><strong>Cancelled:</strong> {{ $event->cancellation_reason }}</div>
    @endif

    @foreach ($moduleWarnings as $warning)<div class="alert alert-warning" role="status">{{ $warning }}</div>@endforeach

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card mb-4" aria-labelledby="event-summary-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="event-summary-title">Event summary</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Client</dt><dd class="col-sm-8">@if($event->client)<a href="{{ route('clients.show', $event->client) }}">{{ $event->client->display_name }}</a>@else Not assigned (Draft only) @endif</dd>
                        <dt class="col-sm-4">Schedule</dt><dd class="col-sm-8">{{ $organizationStart->format('Y-m-d H:i') }} – {{ $organizationEnd->format('Y-m-d H:i T') }}</dd>
                        @if ($event->timezone !== $organizationTimezone)
                            <dt class="col-sm-4">Event-local time</dt><dd class="col-sm-8">{{ $eventStart->format('Y-m-d H:i') }} – {{ $eventEnd->format('Y-m-d H:i T') }}</dd>
                        @endif
                        <dt class="col-sm-4">Organizer / manager</dt><dd class="col-sm-8">{{ $event->manager?->name ?? 'Unassigned' }}</dd>
                        <dt class="col-sm-4">Branch</dt><dd class="col-sm-8">{{ $event->branch?->name ?? 'Unscoped' }}</dd>
                        <dt class="col-sm-4">Booking</dt><dd class="col-sm-8">
                            @if ($event->booking)
                                @can('view', $event->booking)<a href="{{ route('bookings.show', $event->booking) }}">{{ $event->booking->reference_number }}</a>@else Linked booking @endcan
                            @else
                                Not used — direct creation
                            @endif
                        </dd>
                        <dt class="col-sm-4">Template</dt><dd class="col-sm-8">{{ $event->template?->name ?? 'Custom / none' }} @if($event->template_snapshot)<span class="small text-secondary">(creation snapshot preserved)</span>@endif</dd>
                        <dt class="col-sm-4">Expected guests</dt><dd class="col-sm-8">{{ $event->expected_guest_count ?? '—' }}</dd>
                        <dt class="col-sm-4">Budget estimate</dt><dd class="col-sm-8">{{ $event->core_budget_estimate !== null ? number_format((float) $event->core_budget_estimate, 2).' '.$event->currency_code : '—' }}</dd>
                        <dt class="col-sm-4">Theme</dt><dd class="col-sm-8">{{ $event->theme ?: '—' }}</dd>
                        <dt class="col-sm-4">Dress code</dt><dd class="col-sm-8">{{ $event->dress_code ?: '—' }}</dd>
                        <dt class="col-sm-4">Description</dt><dd class="col-sm-8 mb-0">{{ $event->description ?: '—' }}</dd>
                    </dl>
                </div>
            </section>

            <section class="card mb-4" aria-labelledby="modules-title">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <h2 class="h4 mb-0" id="modules-title">Event workspaces</h2>
                        @can('manageModules', $event)<a class="btn btn-sm btn-outline-primary" href="{{ route('events.modules.edit', $event) }}">Manage modules</a>@endcan
                    </div>
                    @if ($workspaceModules->isEmpty())
                        <x-ui.empty-state title="No optional workspaces available" description="The core Client-to-Event workflow remains available and can complete independently." class="mt-3"/>
                    @else
                        <div class="row g-3 mt-1">
                            @foreach ($workspaceModules as $setting)
                                <div class="col-md-6">
                                    <a class="card h-100 text-decoration-none module-workspace-card" href="{{ route('events.workspace.module', [$event, $setting->module_key]) }}">
                                        <span class="card-body">
                                            <span class="d-block fw-semibold text-body">{{ $setting->definition?->display_label ?? str($setting->module_key)->headline() }}</span>
                                            <span class="d-block small text-secondary mt-1">Open enabled workspace</span>
                                        </span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="card mb-4" aria-labelledby="notes-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="notes-title">Planning notes</h2>
                    @forelse ($event->notes as $note)
                        <article class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between gap-3">
                                <span>@if($note->is_pinned)<span class="badge text-bg-primary">Pinned</span>@endif @if($note->include_in_duplicate)<span class="badge text-bg-light border">Eligible for duplication</span>@endif</span>
                                <small class="text-secondary">{{ $note->author?->name ?? 'System' }} · {{ $note->created_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}</small>
                            </div>
                            <p class="mb-0 mt-2">{{ $note->body }}</p>
                        </article>
                    @empty
                        <p class="text-secondary">No planning notes yet.</p>
                    @endforelse
                    @can('addNote', $event)
                        <hr>
                        <form method="POST" action="{{ route('events.notes.store', $event) }}">
                            @csrf
                            <x-ui.form.textarea name="body" label="Add note" rows="3" required/>
                            <div class="d-flex flex-wrap gap-4 mb-3">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="is_pinned"><label class="form-check-label" for="is_pinned">Pin note</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="include_in_duplicate" value="1" id="include_in_duplicate"><label class="form-check-label" for="include_in_duplicate">Allow copying when this Event is duplicated</label></div>
                            </div>
                            <button class="btn btn-primary" type="submit">Add note</button>
                        </form>
                    @endcan
                </div>
            </section>

            <section class="card mb-4" aria-labelledby="history-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="history-title">Status history</h2>
                    <x-ui.data-table :columns="[['label' => 'Change'], ['label' => 'Actor'], ['label' => 'When'], ['label' => 'Reason']]" caption="Event status history" :empty="$event->statusHistory->isEmpty()" empty-title="No status history">
                        @foreach ($event->statusHistory as $history)
                            <tr>
                                <td>{{ $history->from_status ? str($history->from_status)->headline().' → ' : '' }}{{ str($history->to_status)->headline() }}</td>
                                <td>{{ $history->actor?->name ?? 'System' }}</td>
                                <td>{{ $history->changed_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}</td>
                                <td>{{ $history->reason ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </x-ui.data-table>
                </div>
            </section>

            <section class="card" aria-labelledby="timeline-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="timeline-title">Activity timeline</h2>
                    <x-ui.data-table :columns="[['label' => 'Activity'], ['label' => 'Actor'], ['label' => 'When']]" caption="Event activity timeline" :empty="$event->timelineItems->isEmpty()" empty-title="No timeline activity">
                        @foreach ($event->timelineItems as $item)
                            <tr>
                                <td><span class="fw-semibold">{{ $item->title }}</span>@if($item->description)<span class="d-block small text-secondary">{{ $item->description }}</span>@endif</td>
                                <td>{{ $item->actor?->name ?? 'System' }}</td>
                                <td>{{ $item->occurred_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}</td>
                            </tr>
                        @endforeach
                    </x-ui.data-table>
                </div>
            </section>
        </div>

        <aside class="col-xl-4">
            <section class="card mb-4">
                <div class="card-body p-4">
                    <h2 class="h4">Primary contact</h2>
                    <dl class="mb-0">
                        <dt>Name</dt><dd>{{ $event->primary_contact_name ?: '—' }}</dd>
                        <dt>Email</dt><dd>{{ $event->primary_contact_email ?: '—' }}</dd>
                        <dt>Phone</dt><dd class="mb-0">{{ $event->primary_contact_phone ?: '—' }}</dd>
                    </dl>
                </div>
            </section>

            @if ($availableTransitions !== [])
                @can('transition', $event)
                    <section class="card mb-4">
                        <div class="card-body p-4">
                            <h2 class="h4">Status transition</h2>
                            @foreach ($availableTransitions as $transition)
                                @if ($transition === 'cancelled')
                                    <form class="border-top pt-3 mt-3" method="POST" action="{{ route('events.status', $event) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="cancelled">
                                        <x-ui.form.textarea name="reason" label="Cancellation reason" rows="3" required/>
                                        <button class="btn btn-outline-danger" type="submit">Cancel event</button>
                                    </form>
                                @else
                                    <form class="mb-2" method="POST" action="{{ route('events.status', $event) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $transition }}">
                                        <button class="btn btn-success w-100" type="submit">Move to {{ str($transition)->headline() }}</button>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endcan
            @endif

            @can('correct', $event)
                <section class="card border-info mb-4">
                    <div class="card-body p-4">
                        <h2 class="h4">Privileged correction</h2>
                        <p class="text-secondary">Reopens the completed Event in Planning and records the reason, actor, and time.</p>
                        <form method="POST" action="{{ route('events.correction', $event) }}">
                            @csrf
                            <x-ui.form.textarea name="reason" label="Correction reason" rows="3" required/>
                            <button class="btn btn-info" type="submit">Reopen in Planning</button>
                        </form>
                    </div>
                </section>
            @endcan

            @can('duplicate', $event)
                <section class="card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h4">Duplicate planning setup</h2>
                        <p class="text-secondary">Creates an independent Draft. Financial records, attendance, scans, and prior history are excluded.</p>
                        <form method="POST" action="{{ route('events.duplicate', $event) }}">
                            @csrf
                            <x-ui.form.input name="name" label="New event name" :value="$event->name.' (Copy)'" required/>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="copy_notes" value="1" id="copy_notes"><label class="form-check-label" for="copy_notes">Copy only notes explicitly marked eligible</label></div>
                            <button class="btn btn-outline-primary" type="submit">Duplicate event</button>
                        </form>
                    </div>
                </section>
            @endcan

            @if (! $event->archived_at)
                @can('archive', $event)
                    <section class="card mb-4">
                        <div class="card-body p-4">
                            <h2 class="h4">Archive event</h2>
                            <p class="text-secondary">Archiving preserves lifecycle, planning, and audit history.</p>
                            <form method="POST" action="{{ route('events.archive', $event) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="action" value="archive">
                                <x-ui.form.textarea name="reason" label="Reason" rows="3" required/>
                                <button class="btn btn-warning" type="submit">Archive event</button>
                            </form>
                        </div>
                    </section>
                @endcan
            @else
                @can('reactivate', $event)
                    <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Reactivate event</h2><form method="POST" action="{{ route('events.archive', $event) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="reactivate"><x-ui.form.textarea name="reason" label="Reason" rows="3"/><button class="btn btn-success" type="submit">Reactivate event</button></form></div></section>
                @endcan
            @endif
        </aside>
    </div>
</x-layouts.app>
