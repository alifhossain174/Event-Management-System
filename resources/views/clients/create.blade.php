<x-layouts.app
    title="Create client"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Clients', 'url' => route('clients.index')],
        ['label' => 'Create'],
    ]"
>
    <x-ui.page-header title="Create client" subtitle="Create an individual or organization record. A portal user is not required."/>
    <form method="POST" action="{{ route('clients.store') }}">
        @csrf
        @include('clients._form')
    </form>
</x-layouts.app>
