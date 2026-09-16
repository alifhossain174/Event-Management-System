<x-layouts.app :title="$managedUser->name">
    <main class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h2 mb-1">{{ $managedUser->name }}</h1>
                <p class="text-secondary mb-0">{{ $managedUser->email }}</p>
            </div>
            @can('update', $managedUser)
                <a class="btn btn-outline-primary" href="{{ route('users.edit', $managedUser) }}">Edit user</a>
            @endcan
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="card mb-4">
                    <div class="card-body">
                        <h2 class="h4">Access</h2>
                        <p><strong>Roles:</strong> {{ $managedUser->roles->pluck('name')->join(', ') ?: 'None' }}</p>
                        <p><strong>Status:</strong> {{ $managedUser->is_active ? 'Active' : 'Inactive' }}</p>
                        <p class="mb-0"><strong>Last login:</strong> {{ $managedUser->last_login_at?->format('Y-m-d H:i T') ?? 'Never' }}</p>
                    </div>
                </section>

                <section class="card">
                    <div class="card-body">
                        <h2 class="h4">Status history</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Change</th><th>Actor</th><th>When</th><th>Reason</th></tr></thead>
                                <tbody>
                                    @forelse ($managedUser->statusHistory->sortByDesc('changed_at') as $history)
                                        <tr>
                                            <td>{{ ucfirst($history->from_status) }} → {{ ucfirst($history->to_status) }}</td>
                                            <td>{{ $history->actor?->name ?? 'System' }}</td>
                                            <td>{{ $history->changed_at->format('Y-m-d H:i T') }}</td>
                                            <td>{{ $history->reason ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="text-secondary" colspan="4">No status changes recorded.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                @can('changeStatus', $managedUser)
                    <section class="card mb-4">
                        <div class="card-body">
                            <h2 class="h4">{{ $managedUser->is_active ? 'Deactivate' : 'Activate' }} account</h2>
                            <form method="POST" action="{{ route('users.status.update', $managedUser) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $managedUser->is_active ? 'inactive' : 'active' }}">
                                <label class="form-label" for="status-reason">Reason (optional)</label>
                                <textarea class="form-control mb-3" id="status-reason" name="reason" rows="3"></textarea>
                                <button class="btn {{ $managedUser->is_active ? 'btn-warning' : 'btn-success' }}" type="submit">
                                    {{ $managedUser->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </div>
                    </section>
                @endcan

                @can('delete', $managedUser)
                    <section class="card border-danger">
                        <div class="card-body">
                            <h2 class="h4 text-danger">Archive account</h2>
                            <p class="text-secondary">Archived accounts cannot authenticate and remain available in audit history.</p>
                            <form method="POST" action="{{ route('users.destroy', $managedUser) }}">
                                @csrf
                                @method('DELETE')
                                <label class="form-label" for="archive-reason">Reason (optional)</label>
                                <textarea class="form-control mb-3" id="archive-reason" name="reason" rows="3"></textarea>
                                <button class="btn btn-danger" type="submit">Archive user</button>
                            </form>
                        </div>
                    </section>
                @endcan
            </div>
        </div>
    </main>
</x-layouts.app>
