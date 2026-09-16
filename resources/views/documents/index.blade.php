<x-layouts.app title="Documents" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Documents']]">
    <x-ui.page-header title="Documents" subtitle="Private, versioned files delivered only after server-side authorization.">
        <x-slot:actions>@can('create', App\Models\Document::class)<a class="btn btn-primary" href="{{ route('documents.create') }}">Upload document</a>@endcan</x-slot:actions>
    </x-ui.page-header>
    <x-ui.filter-bar :action="route('documents.index')" :clear-url="route('documents.index')">
        <div class="col-12 col-lg-4"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Title or description"></div>
        <div class="col-12 col-md-4 col-lg-3"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)($filters['category'] ?? '') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-6 col-md-4 col-lg-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="archived" @selected(($filters['status'] ?? '') === 'archived')>Archived</option></select></div>
        <div class="col-6 col-md-4 col-lg-2"><label class="form-label" for="expiry">Expiry</label><select class="form-select" id="expiry" name="expiry"><option value="">Any</option><option value="upcoming" @selected(($filters['expiry'] ?? '') === 'upcoming')>Next 30 days</option><option value="expired" @selected(($filters['expiry'] ?? '') === 'expired')>Expired</option><option value="none" @selected(($filters['expiry'] ?? '') === 'none')>No expiry</option></select></div>
    </x-ui.filter-bar>
    <x-ui.data-table :columns="[['label' => 'Document'], ['label' => 'Category'], ['label' => 'Version'], ['label' => 'Expiry'], ['label' => 'Status'], ['label' => 'Actions', 'class' => 'text-end']]" caption="Protected documents" :empty="$documents->isEmpty()" empty-title="No documents match these filters" empty-description="Upload a protected file or clear the current filters.">
        @foreach($documents as $document)<tr>
            <td><strong class="d-block">{{ $document->title }}</strong><span class="small text-secondary">{{ $document->currentVersion?->original_name }}</span></td>
            <td>{{ $document->category?->name ?? 'Uncategorized' }}</td><td>v{{ $document->currentVersion?->version_number ?? '—' }}</td>
            <td>{{ $document->expiry_date?->format('Y-m-d') ?? '—' }}</td><td><x-ui.status-badge :status="$document->status"/></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('documents.show', $document) }}">View</a></td>
        </tr>@endforeach
    </x-ui.data-table>
    <x-ui.pagination :paginator="$documents"/>
</x-layouts.app>
