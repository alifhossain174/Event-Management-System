<x-layouts.app
    :title="$client->display_name"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Clients', 'url' => route('clients.index')],
        ['label' => $client->display_name],
    ]"
>
    <x-ui.page-header :title="$client->display_name" :subtitle="ucfirst($client->type).' client · '.ucfirst($client->status)">
        <x-slot:actions>
            @can('update', $client)
                @if ($client->status !== 'merged')
                    <a class="btn btn-outline-primary" href="{{ route('clients.edit', $client) }}">Edit client</a>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($client->status === 'merged' && $client->mergedInto)
        <div class="alert alert-info" role="status">
            This record was merged into <a class="alert-link" href="{{ route('clients.show', $client->mergedInto) }}">{{ $client->mergedInto->display_name }}</a>. It is retained for history and cannot be edited.
        </div>
    @endif

    @if ($duplicates->isNotEmpty())
        <div class="alert alert-warning" role="status">
            <strong>Possible duplicate:</strong>
            @foreach ($duplicates as $duplicate)
                <a class="alert-link" href="{{ route('clients.show', $duplicate) }}">{{ $duplicate->display_name }}</a>@unless($loop->last), @endunless
            @endforeach
            Matching contact details are a warning only because families and organizations may share them.
        </div>
    @endif

    <div class="row g-3 mb-4" aria-label="Client activity summary">
        @foreach ($summary as $key => $item)
            <div class="col-6 col-md-4 col-xl-2">
                <section class="card h-100">
                    <div class="card-body">
                        <span class="d-block text-secondary small">{{ $item['label'] }}</span>
                        <strong class="fs-4">{{ $item['implemented'] ? $item['count'] : '—' }}</strong>
                        @if (! $item['implemented'])
                            <span class="d-block small text-secondary">Coming in a later module</span>
                        @elseif ($key === 'documents')
                            <a class="stretched-link small" href="#client-documents"><span class="visually-hidden">View </span>View</a>
                        @else
                            <span class="d-block small text-secondary">Available when module UI is added</span>
                        @endif
                    </div>
                </section>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card mb-4" aria-labelledby="client-details-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="client-details-title">Client details</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Type</dt><dd class="col-sm-8">{{ ucfirst($client->type) }}</dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $client->primary_email ?: '—' }}</dd>
                        <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $client->primary_phone ?: '—' }}</dd>
                        <dt class="col-sm-4">Branch</dt><dd class="col-sm-8">{{ $client->branch?->name ?? 'Unscoped' }}</dd>
                        <dt class="col-sm-4">Portal access</dt><dd class="col-sm-8">{{ $client->user?->email ?? 'Not linked' }}</dd>
                        @if ($client->type === 'organization')
                            <dt class="col-sm-4">Legal name</dt><dd class="col-sm-8">{{ $client->legal_name ?: '—' }}</dd>
                            <dt class="col-sm-4">Registration</dt><dd class="col-sm-8">{{ $client->registration_number ?: '—' }}</dd>
                            <dt class="col-sm-4">Tax identifier</dt><dd class="col-sm-8">{{ $client->tax_identifier ?: '—' }}</dd>
                        @endif
                        <dt class="col-sm-4">Address</dt>
                        <dd class="col-sm-8">
                            {{ collect([$client->address_line_1, $client->address_line_2, $client->city, $client->state_region, $client->postal_code, $client->country_code])->filter()->join(', ') ?: '—' }}
                        </dd>
                        <dt class="col-sm-4">Notes</dt><dd class="col-sm-8 mb-0">{{ $client->notes ?: '—' }}</dd>
                    </dl>
                </div>
            </section>

            <section class="card mb-4" aria-labelledby="contacts-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="contacts-title">Contacts</h2>
                    <x-ui.data-table
                        :columns="[
                            ['label' => 'Contact'], ['label' => 'Details'], ['label' => 'Role'], ['label' => 'Actions'],
                        ]"
                        caption="Client contacts"
                        :empty="$client->contacts->isEmpty()"
                        empty-title="No additional contacts"
                        empty-description="Add coordinators, family members, assistants, or other contacts as needed."
                    >
                        @foreach ($client->contacts as $contact)
                            <tr>
                                <td>{{ $contact->name }} @if($contact->is_primary)<span class="badge text-bg-primary">Primary</span>@endif</td>
                                <td>{{ $contact->email ?: '—' }}<span class="d-block small text-secondary">{{ $contact->phone ?: '—' }}</span></td>
                                <td>{{ $contact->job_title ?: $contact->relationship_label ?: '—' }}</td>
                                <td>
                                    @can('update', $client)
                                        <div class="d-flex flex-wrap gap-1">
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.contacts.edit', [$client, $contact]) }}">Edit</a>
                                            <form method="POST" action="{{ route('clients.contacts.destroy', [$client, $contact]) }}">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Archive</button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.data-table>

                    @can('update', $client)
                        @if ($client->status !== 'merged')
                            <hr>
                            <h3 class="h5">Add contact</h3>
                            <form method="POST" action="{{ route('clients.contacts.store', $client) }}">
                                @csrf
                                @include('clients.contacts.fields', ['contact' => null])
                                <button class="btn btn-primary" type="submit">Add contact</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </section>

            <section class="card mb-4" id="client-documents" aria-labelledby="documents-title">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <h2 class="h4 mb-0" id="documents-title">Documents</h2>
                        @can('create', App\Models\Document::class)
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('documents.create') }}">Add document</a>
                        @endcan
                    </div>
                    <p class="text-secondary mt-2">Client-linked files are delivered only through authorized private download routes.</p>
                    <x-ui.data-table
                        :columns="[['label' => 'Title'], ['label' => 'Status'], ['label' => 'Action']]"
                        caption="Client documents"
                        :empty="$client->documentLinks->isEmpty()"
                        empty-title="No documents linked"
                    >
                        @foreach ($client->documentLinks as $link)
                            <tr>
                                <td>{{ $link->document->title }}</td>
                                <td><x-ui.status-badge :status="$link->document->status"/></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('documents.show', $link->document) }}">View</a></td>
                            </tr>
                        @endforeach
                    </x-ui.data-table>
                </div>
            </section>

            <section aria-labelledby="client-history-title">
                <h2 class="h4" id="client-history-title">Status history</h2>
                <x-ui.data-table
                    :columns="[['label' => 'Change'], ['label' => 'Actor'], ['label' => 'When'], ['label' => 'Reason']]"
                    caption="Client status history"
                    :empty="$client->statusHistory->isEmpty()"
                    empty-title="No status changes recorded"
                >
                    @foreach ($client->statusHistory->sortByDesc('changed_at') as $history)
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

        <aside class="col-xl-4">
            @can('assignUser', $client)
                @if ($client->status !== 'merged')
                    <section class="card mb-4">
                        <div class="card-body p-4">
                            <h2 class="h4">Portal user link</h2>
                            <p class="text-secondary">Optional and privileged. Creating a client never creates a login account.</p>
                            <form method="POST" action="{{ route('clients.user-link', $client) }}">
                                @csrf @method('PUT')
                                <x-ui.form.select
                                    name="user_id"
                                    label="Client-role user"
                                    :options="$portalUsers->pluck('email', 'id')"
                                    :value="$client->user_id"
                                    placeholder="No linked user"
                                />
                                <button class="btn btn-outline-primary" type="submit">Update link</button>
                            </form>
                        </div>
                    </section>
                @endif
            @endcan

            @if ($client->status === 'active')
                @can('archive', $client)
                    <section class="card mb-4">
                        <div class="card-body p-4">
                            <h2 class="h4">Archive client</h2>
                            <p class="text-secondary">This preserves all history and links. Destructive deletion is not exposed.</p>
                            <form method="POST" action="{{ route('clients.status', $client) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="action" value="archive">
                                <x-ui.form.textarea name="reason" label="Reason" rows="3" required/>
                                <button class="btn btn-warning" type="submit">Archive client</button>
                            </form>
                        </div>
                    </section>
                @endcan
            @elseif ($client->status === 'archived')
                @can('reactivate', $client)
                    <section class="card mb-4">
                        <div class="card-body p-4">
                            <h2 class="h4">Reactivate client</h2>
                            <form method="POST" action="{{ route('clients.status', $client) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="action" value="reactivate">
                                <x-ui.form.textarea name="reason" label="Reason" rows="3"/>
                                <button class="btn btn-success" type="submit">Reactivate client</button>
                            </form>
                        </div>
                    </section>
                @endcan
            @endif

            @can('merge', $client)
                @if ($client->status !== 'merged' && $mergeTargets->isNotEmpty())
                    <section class="card border-warning">
                        <div class="card-body p-4">
                            <h2 class="h4">Merge duplicate</h2>
                            <p class="text-secondary">Contacts and document links move to the target; this source remains as an auditable merged record.</p>
                            <form method="POST" action="{{ route('clients.merge', $client) }}">
                                @csrf
                                <x-ui.form.select
                                    name="target_client_id"
                                    label="Target client"
                                    :options="$mergeTargets->pluck('display_name', 'id')"
                                    placeholder="Select target"
                                    required
                                />
                                <x-ui.form.textarea name="reason" label="Reason" rows="3" required/>
                                <button class="btn btn-warning" type="submit">Merge into target</button>
                            </form>
                        </div>
                    </section>
                @endif
            @endcan
        </aside>
    </div>
</x-layouts.app>
