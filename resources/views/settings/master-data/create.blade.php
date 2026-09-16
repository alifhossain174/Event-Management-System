<x-layouts.app :title="'Add '.$definition['singular']" :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => $definition['label'], 'url' => route('settings.master-data.index', $type)], ['label' => 'Add']]">
    <x-ui.page-header :title="'Add '.$definition['singular']" subtitle="Create a configurable lookup value for later modules."/>
    @include('settings.master-data._tabs')
    <form class="card" method="POST" action="{{ route('settings.master-data.store', $type) }}">@csrf<div class="card-body">@include('settings.master-data._form')</div><div class="card-footer bg-white d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="{{ route('settings.master-data.index', $type) }}">Cancel</a><button class="btn btn-primary" type="submit">Create category</button></div></form>
</x-layouts.app>
