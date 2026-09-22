<x-layouts.app
    title="Events"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events'],
    ]"
>
    <x-ui.page-header title="Events" subtitle="Manage the direct Client-to-Event workflow; a Booking is never required.">
        <x-slot:actions>
            @can('create', App\Models\Event::class)
                <a class="btn btn-primary" href="{{ route('events.create') }}">Create event</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('events.index')" :clear-url="route('events.index')">
        <div class="col-12 col-xl-4">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text"><x-ui.icon name="search" :size="17"/></span>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, event, or client">
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                @foreach (App\Models\Event::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="view">Schedule</label>
            <select class="form-select" id="view" name="view">
                <option value="">All schedules</option>
                <option value="upcoming" @selected(($filters['view'] ?? '') === 'upcoming')>Upcoming only</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="category">Category</label>
            <select class="form-select" id="category" name="category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="client">Client</label>
            <select class="form-select" id="client" name="client">
                <option value="">All clients</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) ($filters['client'] ?? '') === (string) $client->id)>{{ $client->display_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="archive">Records</label>
            <select class="form-select" id="archive" name="archive">
                <option value="active" @selected(($filters['archive'] ?? 'active') === 'active')>Active</option>
                <option value="archived" @selected(($filters['archive'] ?? '') === 'archived')>Archived</option>
            </select>
        </div>
        @if ($branches->isNotEmpty())
            <div class="col-12 col-sm-6 col-xl-3">
                <label class="form-label" for="branch">Branch</label>
                <select class="form-select" id="branch" name="branch">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) ($filters['branch'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-12 col-sm-6 col-xl-3">
            <label class="form-label" for="manager">Manager</label>
            <select class="form-select" id="manager" name="manager">
                <option value="">All managers</option>
                @foreach ($managers as $manager)
                    <option value="{{ $manager->id }}" @selected((string) ($filters['manager'] ?? '') === (string) $manager->id)>{{ $manager->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="date_from">Starts from</label>
            <input class="form-control" id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="date_to">Starts through</label>
            <input class="form-control" id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
        </div>
    </x-ui.filter-bar>

    <x-ui.data-table
        :columns="[
            ['label' => 'Event'], ['label' => 'Client'], ['label' => 'Schedule'],
            ['label' => 'Category'], ['label' => 'Status'], ['label' => 'Actions', 'class' => 'table-actions'],
        ]"
        caption="Event records"
        :empty="$events->isEmpty()"
        empty-title="No events match these filters"
        empty-description="Change the filters or create the first direct Event."
    >
        @foreach ($events as $event)
            <tr>
                <td>
                    <a class="fw-semibold text-decoration-none" href="{{ route('events.show', $event) }}">{{ $event->name }}</a>
                    <span class="d-block small text-secondary">{{ $event->reference_number }}</span>
                </td>
                <td>{{ $event->client?->display_name ?? 'Not assigned' }}</td>
                <td>
                    {{ $event->starts_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }}
                    <span class="d-block small text-secondary">{{ $organizationTimezone }}</span>
                </td>
                <td>{{ $event->category->name }}</td>
                <td><x-ui.status-badge :status="$event->status"/></td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('events.show', $event) }}">View</a>
                        @can('update', $event)<a class="btn btn-sm btn-outline-secondary" href="{{ route('events.edit', $event) }}">Edit</a>@endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>

    <x-ui.pagination :paginator="$events"/>
</x-layouts.app>
