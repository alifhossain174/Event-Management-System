<x-layouts.app
    title="Create user"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Users', 'url' => route('users.index')],
        ['label' => 'Create user'],
    ]"
>
    <x-ui.page-header title="Create user" subtitle="Create a login account and assign at least one role."/>
    <div class="card mx-auto" style="max-width: 48rem">
        <div class="card-body p-4">
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf
                    @include('users._form', ['submitLabel' => 'Create user'])
                </form>
        </div>
    </div>
</x-layouts.app>
