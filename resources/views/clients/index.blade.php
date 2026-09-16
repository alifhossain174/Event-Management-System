<x-layouts.app
    title="Clients"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Clients'],
    ]"
>
    <x-ui.page-header title="Clients" subtitle="Manage individual and organization customer records without requiring portal accounts.">
        <x-slot:actions>
            @can('create', App\Models\Client::class)
                <a class="btn btn-primary" href="{{ route('clients.create') }}">Create client</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('clients.index')" :clear-url="route('clients.index')">
        <div class="col-12 col-xl-4">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text"><x-ui.icon name="search" :size="17"/></span>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, phone, or contact">
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="type">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="">All types</option>
                <option value="individual" @selected(($filters['type'] ?? '') === 'individual')>Individual</option>
                <option value="organization" @selected(($filters['type'] ?? '') === 'organization')>Organization</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Active</option>
                @foreach (['active' => 'Active', 'archived' => 'Archived', 'merged' => 'Merged'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
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
    </x-ui.filter-bar>

    <x-ui.data-table
        :columns="[
            ['label' => 'Client'],
            ['label' => 'Contact'],
            ['label' => 'Type'],
            ['label' => 'Branch'],
            ['label' => 'Status'],
            ['label' => 'Actions', 'class' => 'table-actions'],
        ]"
        caption="Client master records"
        :empty="$clients->isEmpty()"
        empty-title="No clients match these filters"
        empty-description="Change the filters or create the first client record."
    >
        @foreach ($clients as $client)
            <tr>
                <td>
                    <a class="fw-semibold text-decoration-none" href="{{ route('clients.show', $client) }}">{{ $client->display_name }}</a>
                    @if ($client->user)<span class="d-block small text-secondary">Portal: {{ $client->user->email }}</span>@endif
                </td>
                <td>
                    <span class="d-block">{{ $client->primary_email ?: '—' }}</span>
                    <span class="small text-secondary">{{ $client->primary_phone ?: '—' }}</span>
                </td>
                <td>{{ ucfirst($client->type) }}</td>
                <td>{{ $client->branch?->name ?? 'Unscoped' }}</td>
                <td><x-ui.status-badge :status="$client->status"/></td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('clients.show', $client) }}">View</a>
                        @can('update', $client)
                            @if ($client->status !== 'merged')
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.edit', $client) }}">Edit</a>
                            @endif
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>

    <x-ui.pagination :paginator="$clients"/>
</x-layouts.app>
