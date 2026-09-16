<x-layouts.app :title="$document->title" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Documents', 'url' => route('documents.index')], ['label' => $document->title]]">
    <x-ui.page-header :title="$document->title" :subtitle="$document->category?->name ?? 'Uncategorized'">
        <x-slot:actions>@can('download', $document)<a class="btn btn-primary" href="{{ route('documents.download', $document) }}">Download current version</a>@endcan</x-slot:actions>
    </x-ui.page-header>
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Details</h2><dl class="row mb-0">
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><x-ui.status-badge :status="$document->status"/></dd>
                <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $document->description ?: '—' }}</dd>
                <dt class="col-sm-3">Expiry</dt><dd class="col-sm-9">{{ $document->expiry_date?->format('Y-m-d') ?? '—' }}</dd>
                <dt class="col-sm-3">Contexts</dt><dd class="col-sm-9 mb-0">{{ $document->links->map(fn($link) => class_basename($link->linkable_type).' #'.$link->linkable_id)->join(', ') ?: '—' }}</dd>
            </dl></div></section>
            <section aria-labelledby="versions-title"><h2 class="h4" id="versions-title">Version history</h2>
                <x-ui.data-table :columns="[['label' => 'Version'], ['label' => 'File'], ['label' => 'Uploaded'], ['label' => 'Notes'], ['label' => 'Actions', 'class' => 'text-end']]" caption="Immutable document versions" :empty="$document->versions->isEmpty()" empty-title="No versions found">
                    @foreach($document->versions as $version)<tr><td>v{{ $version->version_number }}</td><td>{{ $version->original_name }}<span class="d-block small text-secondary">{{ $version->mime_type }} · {{ number_format($version->size_bytes / 1024, 1) }} KB</span></td><td>{{ $version->created_at->format('Y-m-d H:i T') }}<span class="d-block small text-secondary">{{ $version->uploadedBy?->name ?? 'System' }}</span></td><td>{{ $version->notes ?: '—' }}</td><td class="text-end">@can('download', $document)<a class="btn btn-sm btn-outline-primary" href="{{ route('documents.versions.download', [$document, $version]) }}">Download</a>@endcan</td></tr>@endforeach
                </x-ui.data-table>
            </section>
        </div>
        <div class="col-lg-4">
            @can('update', $document) @if($document->status === 'active')<section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Add version</h2><p class="text-secondary">The current and prior file remain in immutable history.</p><form method="POST" enctype="multipart/form-data" action="{{ route('documents.versions.store', $document) }}">@csrf<label class="form-label" for="file">Replacement file</label><input class="form-control mb-3" type="file" id="file" name="file" required><x-ui.form.textarea name="version_notes" label="Version notes" rows="3" required/><button class="btn btn-outline-primary" type="submit">Store new version</button></form></div></section>@endif @endcan
            @can('delete', $document) @if($document->status === 'active')<section class="card border-warning"><div class="card-body p-4"><h2 class="h4">Archive document</h2><p class="text-secondary">Downloads remain protected and all versions/history are retained.</p><form id="archive-document" method="POST" action="{{ route('documents.destroy', $document) }}">@csrf @method('DELETE')<x-ui.form.textarea name="reason" label="Reason" rows="3"/><button class="btn btn-warning" type="button" data-bs-toggle="modal" data-bs-target="#confirmationModal" data-confirm-form="archive-document" data-confirm-title="Archive document?" data-confirm-message="The document will leave active workflows but its versions and history remain." data-confirm-button="Archive">Archive</button></form></div></section>@endif @endcan
        </div>
    </div>
</x-layouts.app>
