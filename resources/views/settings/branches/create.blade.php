<x-layouts.app title="Add branch" :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Branches', 'url' => route('settings.branches.index')], ['label' => 'Add branch']]">
    <x-ui.page-header title="Add branch" subtitle="Branch selection remains optional until branch mode is deliberately enabled."/>
    <form class="card" method="POST" action="{{ route('settings.branches.store') }}">
        @csrf
        <div class="card-body">@include('settings.branches._form')</div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="{{ route('settings.branches.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Create branch</button></div>
    </form>
</x-layouts.app>
