<x-layouts.app title="Create user">
    <main class="container py-4">
        <div class="card mx-auto" style="max-width: 48rem">
            <div class="card-body p-4">
                <h1 class="h2 mb-4">Create user</h1>
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf
                    @include('users._form', ['submitLabel' => 'Create user'])
                </form>
            </div>
        </div>
    </main>
</x-layouts.app>
