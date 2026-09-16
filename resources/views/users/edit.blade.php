<x-layouts.app title="Edit user">
    <main class="container py-4">
        <div class="card mx-auto" style="max-width: 48rem">
            <div class="card-body p-4">
                <h1 class="h2 mb-4">Edit {{ $managedUser->name }}</h1>
                <form method="POST" action="{{ route('users.update', $managedUser) }}">
                    @csrf
                    @method('PUT')
                    @include('users._form', ['submitLabel' => 'Save changes'])
                </form>
            </div>
        </div>
    </main>
</x-layouts.app>
