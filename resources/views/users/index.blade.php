<x-layouts.app title="Users">
    <main class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 mb-1">Users</h1>
                <p class="text-secondary mb-0">Manage accounts, roles, and activation state.</p>
            </div>
            @can('create', App\Models\User::class)
                <a class="btn btn-primary" href="{{ route('users.create') }}">Create user</a>
            @endcan
        </div>

        <form class="card card-body mb-4" method="GET" action="{{ route('users.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label" for="q">Search</label>
                    <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email">
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Current accounts</option>
                        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->slug }}" @selected(($filters['role'] ?? '') === $role->slug)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 d-grid">
                    <button class="btn btn-outline-primary" type="submit">Filter</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">User</th>
                            <th scope="col">Roles</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="table-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            <tr>
                                <td>
                                    <strong class="d-block">{{ $managedUser->name }}</strong>
                                    <span class="text-secondary">{{ $managedUser->email }}</span>
                                </td>
                                <td>{{ $managedUser->roles->pluck('name')->join(', ') ?: 'None' }}</td>
                                <td>
                                    @if ($managedUser->trashed())
                                        <span class="badge text-bg-secondary">Archived</span>
                                    @elseif ($managedUser->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-warning">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @unless ($managedUser->trashed())
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('users.show', $managedUser) }}">View</a>
                                        @can('update', $managedUser)
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('users.edit', $managedUser) }}">Edit</a>
                                        @endcan
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-secondary py-4" colspan="4">No users match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </main>
</x-layouts.app>
