<x-layouts.app
    title="Branches"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Settings', 'url' => route('settings.edit')],
        ['label' => 'Branches'],
    ]"
>
    <x-ui.page-header title="Branches" subtitle="Prepare optional branch scopes while preserving single-company operation by default.">
        <x-slot:actions>@can('create', App\Models\Branch::class)<a class="btn btn-primary" href="{{ route('settings.branches.create') }}">Add branch</a>@endcan</x-slot:actions>
    </x-ui.page-header>
    @include('settings._navigation')

    <div class="alert {{ $scope->enabled() ? 'alert-warning' : 'alert-info' }}" role="status">
        Branch mode is <strong>{{ $scope->enabled() ? 'enabled' : 'disabled' }}</strong>.
        @unless ($scope->enabled()) Existing and future records may keep a null branch without becoming inaccessible. @endunless
    </div>

    <x-ui.filter-bar :action="route('settings.branches.index')" :clear-url="route('settings.branches.index')">
        <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label" for="q">Search</label>
            <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, code, or city">
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All current</option>
                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select>
        </div>
    </x-ui.filter-bar>

    <x-ui.data-table
        :columns="[['label' => 'Branch'], ['label' => 'Location'], ['label' => 'Time zone'], ['label' => 'Status'], ['label' => 'Actions', 'class' => 'text-end']]"
        caption="Configured branches"
        :empty="$branches->isEmpty()"
        empty-title="No branches found"
        empty-description="Single-company operation works without a branch record."
    >
        @foreach ($branches as $branch)
            <tr>
                <td><strong>{{ $branch->name }}</strong><span class="d-block small text-secondary">{{ $branch->code }}</span></td>
                <td>{{ collect([$branch->city, $branch->country_code])->filter()->join(', ') ?: '—' }}</td>
                <td>{{ $branch->timezone ?: 'Organization default' }}</td>
                <td><x-ui.status-badge :status="$branch->trashed() ? 'archived' : ($branch->is_active ? 'active' : 'inactive')"/></td>
                <td class="text-end">@unless($branch->trashed()) @can('update', $branch)<a class="btn btn-sm btn-outline-primary" href="{{ route('settings.branches.edit', $branch) }}">Edit</a>@endcan @endunless</td>
            </tr>
        @endforeach
    </x-ui.data-table>
    <x-ui.pagination :paginator="$branches" class="mt-4"/>
</x-layouts.app>
