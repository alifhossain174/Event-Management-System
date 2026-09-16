<x-layouts.app title="{{ $definition['label'] }}" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Settings', 'url' => route('settings.edit')], ['label' => 'Master data'], ['label' => $definition['label']]]">
    <x-ui.page-header :title="$definition['label']" subtitle="Maintain configurable categories without application code changes.">
        <x-slot:actions>@can('create', $definition['model'])<a class="btn btn-primary" href="{{ route('settings.master-data.create', $type) }}">Add {{ Str::lower($definition['singular']) }}</a>@endcan</x-slot:actions>
    </x-ui.page-header>
    @include('settings._navigation')
    @include('settings.master-data._tabs')

    <x-ui.filter-bar :action="route('settings.master-data.index', $type)" :clear-url="route('settings.master-data.index', $type)">
        <div class="col-12 col-md-6 col-lg-4"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, slug, or description"></div>
        <div class="col-12 col-md-6 col-lg-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All current</option>@foreach (['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
    </x-ui.filter-bar>

    @php($columns = [['label' => 'Name'], ['label' => 'Slug'], ...(($definition['direction'] ?? false) ? [['label' => 'Direction']] : []), ['label' => 'Order'], ['label' => 'Status'], ['label' => 'Actions', 'class' => 'text-end']])
    <x-ui.data-table :columns="$columns" :caption="$definition['label']" :empty="$categories->isEmpty()" empty-title="No categories found">
        @foreach ($categories as $category)
            <tr>
                <td><strong>{{ $category->name }}</strong>@if($category->description)<span class="d-block small text-secondary">{{ Str::limit($category->description, 80) }}</span>@endif</td>
                <td><code>{{ $category->slug }}</code></td>
                @if($definition['direction'] ?? false)<td>{{ Str::headline($category->direction) }}</td>@endif
                <td>{{ $category->sort_order }}</td>
                <td><x-ui.status-badge :status="$category->trashed() ? 'archived' : ($category->is_active ? 'active' : 'inactive')"/></td>
                <td class="text-end">@unless($category->trashed()) @can('update', $category)<a class="btn btn-sm btn-outline-primary" href="{{ route('settings.master-data.edit', [$type, $category->id]) }}">Edit</a>@endcan @endunless</td>
            </tr>
        @endforeach
    </x-ui.data-table>
    <x-ui.pagination :paginator="$categories" class="mt-4"/>
</x-layouts.app>
