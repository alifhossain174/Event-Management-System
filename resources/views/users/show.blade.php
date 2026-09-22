<x-layouts.app
    :title="$managedUser->name"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Users', 'url' => route('users.index')],
        ['label' => $managedUser->name],
    ]"
>
    <x-ui.page-header :title="$managedUser->name" :subtitle="$managedUser->email">
        <x-slot:actions>
            @can('update', $managedUser)
                <a class="btn btn-outline-primary" href="{{ route('users.edit', $managedUser) }}">Edit user</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="card mb-4">
                <div class="card-body p-4">
                    <h2 class="h4">Access</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Roles</dt>
                        <dd class="col-sm-8">{{ $managedUser->roles->pluck('name')->join(', ') ?: 'None' }}</dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><x-ui.status-badge :status="$managedUser->is_active ? 'active' : 'inactive'"/></dd>
                        <dt class="col-sm-4">Last login</dt>
                        <dd class="col-sm-8 mb-0">{{ $managedUser->last_login_at?->format('Y-m-d H:i T') ?? 'Never' }}</dd>
                    </dl>
                </div>
            </section>

            <section aria-labelledby="status-history-title">
                <h2 class="h4" id="status-history-title">Status history</h2>
                <x-ui.data-table
                    :columns="[
                        ['label' => 'Change'],
                        ['label' => 'Actor'],
                        ['label' => 'When'],
                        ['label' => 'Reason'],
                    ]"
                    caption="Account status history"
                    :empty="$managedUser->statusHistory->isEmpty()"
                    empty-title="No status changes recorded"
                >
                    @foreach ($managedUser->statusHistory->sortByDesc('changed_at') as $history)
                        <tr>
                            <td>{{ ucfirst($history->from_status) }} → {{ ucfirst($history->to_status) }}</td>
                            <td>{{ $history->actor?->name ?? 'System' }}</td>
                            <td>{{ $history->changed_at->format('Y-m-d H:i T') }}</td>
                            <td>{{ $history->reason ?: '—' }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            </section>
        </div>

        <div class="col-lg-5">
            @can('changeStatus', $managedUser)
                <section class="card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h4">{{ $managedUser->is_active ? 'Deactivate' : 'Activate' }} account</h2>
                        <p class="text-secondary">{{ $managedUser->is_active ? 'The user will be signed out and unable to authenticate.' : 'The user will be allowed to authenticate again.' }}</p>
                        <form id="status-form" method="POST" action="{{ route('users.status.update', $managedUser) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $managedUser->is_active ? 'inactive' : 'active' }}">
                            <x-ui.form.textarea name="reason" label="Reason" rows="3" help="Optional, but recommended for the audit history."/>
                            <button
                                class="btn {{ $managedUser->is_active ? 'btn-warning' : 'btn-success' }}"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#confirmationModal"
                                data-confirm-form="status-form"
                                data-confirm-title="{{ $managedUser->is_active ? 'Deactivate account?' : 'Activate account?' }}"
                                data-confirm-message="{{ $managedUser->is_active ? 'This user will immediately lose access to protected screens.' : 'This user will be able to sign in again.' }}"
                                data-confirm-button="{{ $managedUser->is_active ? 'Deactivate user' : 'Activate user' }}"
                                data-confirm-class="{{ $managedUser->is_active ? 'btn-warning' : 'btn-success' }}"
                            >
                                {{ $managedUser->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </div>
                </section>
            @endcan

            @can('delete', $managedUser)
                <section class="card border-danger">
                    <div class="card-body p-4">
                        <h2 class="h4 text-danger">Archive account</h2>
                        <p class="text-secondary">Archived accounts cannot authenticate and remain available in audit history.</p>
                        <form id="archive-form" method="POST" action="{{ route('users.destroy', $managedUser) }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.form.textarea name="reason" label="Reason" rows="3" help="Optional, but recommended for the audit history."/>
                            <button
                                class="btn btn-danger"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#confirmationModal"
                                data-confirm-form="archive-form"
                                data-confirm-title="Archive this user?"
                                data-confirm-message="The account will be disabled and removed from current-user lists. Historical records are preserved."
                                data-confirm-button="Archive user"
                            >Archive user</button>
                        </form>
                    </div>
                </section>
            @endcan
        </div>
    </div>
</x-layouts.app>
