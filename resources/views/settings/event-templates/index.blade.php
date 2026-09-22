<x-layouts.app title="Event templates" wide :breadcrumbs="[['label'=>'Overview','url'=>route('home')],['label'=>'Settings','url'=>route('settings.edit')],['label'=>'Event templates']]">
    <x-ui.page-header title="Event templates" subtitle="Editable starting points that suggest modules and starter content without locking an Event workflow.">
        <x-slot:actions>@can('create',App\Models\EventTemplate::class)<a class="btn btn-primary" href="{{ route('settings.event-templates.create') }}">Create template</a>@endcan</x-slot:actions>
    </x-ui.page-header>
    @include('settings._navigation')
    <x-ui.filter-bar :action="route('settings.event-templates.index')" :clear-url="route('settings.event-templates.index')">
        <div class="col-12 col-lg-5"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q']??'' }}" placeholder="Name, slug, or description"></div>
        <div class="col-6 col-lg-3"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)($filters['category']??'')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">Active</option><option value="archived" @selected(($filters['status']??'')==='archived')>Archived</option></select></div>
    </x-ui.filter-bar>
    <x-ui.data-table :columns="[['label'=>'Template'],['label'=>'Category'],['label'=>'Default modules'],['label'=>'Optional'],['label'=>'Status'],['label'=>'Action']]" caption="Event templates" :empty="$templates->isEmpty()" empty-title="No templates match these filters">
        @foreach($templates as $template)<tr>
            <td><a class="fw-semibold text-decoration-none" href="{{ route('settings.event-templates.show',$template) }}">{{ $template->name }}</a><span class="d-block small text-secondary"><code>{{ $template->slug }}</code></span></td>
            <td>{{ $template->category?->name??'Uncategorized' }}</td>
            <td>{{ $template->modules->where('recommendation','default')->count() }}</td><td>{{ $template->modules->where('recommendation','optional')->count() }}</td>
            <td><x-ui.status-badge :status="$template->status"/></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('settings.event-templates.show',$template) }}">View</a></td>
        </tr>@endforeach
    </x-ui.data-table><x-ui.pagination :paginator="$templates" class="mt-4"/>
</x-layouts.app>
