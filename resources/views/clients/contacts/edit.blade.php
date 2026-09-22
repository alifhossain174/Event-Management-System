<x-layouts.app
    :title="'Edit '.$contact->name"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Clients', 'url' => route('clients.index')],
        ['label' => $client->display_name, 'url' => route('clients.show', $client)],
        ['label' => 'Edit contact'],
    ]"
>
    <x-ui.page-header :title="'Edit '.$contact->name" :subtitle="'Contact for '.$client->display_name"/>
    <form method="POST" action="{{ route('clients.contacts.update', [$client, $contact]) }}" class="card">
        @csrf
        @method('PUT')
        <div class="card-body p-4">
            @include('clients.contacts.fields')
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save contact</button>
                <a class="btn btn-outline-secondary" href="{{ route('clients.show', $client) }}">Cancel</a>
            </div>
        </div>
    </form>
</x-layouts.app>
