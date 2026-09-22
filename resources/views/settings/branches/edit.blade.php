<x-layouts.app title="Edit branch" :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Branches', 'url' => route('settings.branches.index')], ['label' => $branch->name]]">
    <x-ui.page-header :title="$branch->name" subtitle="Update branch contact, address, time zone, and availability."/>
    <form class="card mb-4" method="POST" action="{{ route('settings.branches.update', $branch) }}">
        @csrf
        @method('PUT')
        <div class="card-body">@include('settings.branches._form')</div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="{{ route('settings.branches.index') }}">Back</a><button class="btn btn-primary" type="submit">Save branch</button></div>
    </form>
    @can('delete', $branch)
        <form id="archive-branch-form" method="POST" action="{{ route('settings.branches.destroy', $branch) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#confirmationModal" data-confirm-form="archive-branch-form" data-confirm-title="Archive this branch?" data-confirm-message="Historical references will be preserved and the branch will no longer be available for new assignments." data-confirm-button="Archive branch">Archive branch</button>
        </form>
    @endcan
</x-layouts.app>
