<x-layouts.app
    :title="'Edit '.$client->display_name"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Clients', 'url' => route('clients.index')],
        ['label' => $client->display_name, 'url' => route('clients.show', $client)],
        ['label' => 'Edit'],
    ]"
>
    <x-ui.page-header :title="'Edit '.$client->display_name" subtitle="Changes are recorded in the audit log."/>
    <form method="POST" action="{{ route('clients.update', $client) }}">
        @csrf
        @method('PUT')
        @include('clients._form')
    </form>
</x-layouts.app>
