<x-layouts.app
    title="Users"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Users'],
    ]"
>
    <x-ui.page-header title="Users" subtitle="Manage login accounts, roles, and activation state.">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <a class="btn btn-primary" href="{{ route('users.create') }}">Create user</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('users.index')" :clear-url="route('users.index')">
        <div class="col-12 col-lg-5">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text"><x-ui.icon name="search" :size="17"/></span>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email">
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Current accounts</option>
                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="role">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->slug }}" @selected(($filters['role'] ?? '') === $role->slug)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
    </x-ui.filter-bar>

    <x-ui.data-table
        :columns="[
            ['label' => 'User'],
            ['label' => 'Roles'],
            ['label' => 'Status'],
            ['label' => 'Actions', 'class' => 'table-actions'],
        ]"
        caption="User accounts"
        :empty="$users->isEmpty()"
        empty-title="No users match these filters"
        empty-description="Clear or change the filters to see other accounts."
    >
        @foreach ($users as $managedUser)
            <tr>
                <td>
                    <strong class="d-block">{{ $managedUser->name }}</strong>
                    <span class="text-secondary">{{ $managedUser->email }}</span>
                </td>
                <td>{{ $managedUser->roles->pluck('name')->join(', ') ?: 'None' }}</td>
                <td>
                    @if ($managedUser->trashed())
                        <x-ui.status-badge status="archived"/>
                    @elseif ($managedUser->is_active)
                        <x-ui.status-badge status="active"/>
                    @else
                        <x-ui.status-badge status="inactive"/>
                    @endif
                </td>
                <td>
                    @unless ($managedUser->trashed())
                        <div class="d-flex flex-wrap gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('users.show', $managedUser) }}">View</a>
                            @can('update', $managedUser)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('users.edit', $managedUser) }}">Edit</a>
                            @endcan
                        </div>
                    @endunless
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>

    <x-ui.pagination :paginator="$users"/>
</x-layouts.app>
