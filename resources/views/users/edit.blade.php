<x-layouts.app
    title="Edit user"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Users', 'url' => route('users.index')],
        ['label' => $managedUser->name, 'url' => route('users.show', $managedUser)],
        ['label' => 'Edit'],
    ]"
>
    <x-ui.page-header :title="'Edit '.$managedUser->name" subtitle="Update account details or role assignments."/>
    <div class="card mx-auto" style="max-width: 48rem">
        <div class="card-body p-4">
                <form method="POST" action="{{ route('users.update', $managedUser) }}">
                    @csrf
                    @method('PUT')
                    @include('users._form', ['submitLabel' => 'Save changes'])
                </form>
        </div>
    </div>
</x-layouts.app>
