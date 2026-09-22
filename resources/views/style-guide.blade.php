<x-layouts.app
    title="UI style guide"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'UI style guide'],
    ]"
>
    <x-ui.page-header title="UI style guide" subtitle="Local-only reference for reusable administration patterns.">
        <x-slot:actions>
            <span class="badge text-bg-dark align-self-center">Local / testing only</span>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs :items="[
        ['label' => 'Foundations', 'url' => '#foundations', 'active' => true],
        ['label' => 'Forms', 'url' => '#forms', 'active' => false],
        ['label' => 'Data', 'url' => '#data', 'active' => false],
    ]" class="mb-4"/>

    <section class="mb-5" id="foundations" aria-labelledby="foundations-title">
        <h2 class="h4" id="foundations-title">Foundations</h2>
        <p class="text-secondary">Use semantic headings, concise labels, escaped Blade output, and server-side authorization for every protected action.</p>

        <div class="card">
            <div class="card-body p-4">
                <h3 class="h5">Status badges</h3>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <x-ui.status-badge status="active"/>
                    <x-ui.status-badge status="inactive"/>
                    <x-ui.status-badge status="draft"/>
                    <x-ui.status-badge status="cancelled"/>
                    <x-ui.status-badge status="in-progress">In progress</x-ui.status-badge>
                </div>

                <h3 class="h5">Messages</h3>
                <div class="alert alert-success d-flex gap-2" role="status"><x-ui.icon name="check"/>Changes saved successfully.</div>
                <div class="alert alert-warning d-flex gap-2" role="status"><x-ui.icon name="alert"/>Review this warning before continuing.</div>
                <div class="alert alert-danger d-flex gap-2 mb-0" role="alert"><x-ui.icon name="alert"/>The requested action could not be completed.</div>
            </div>
        </div>
    </section>

    <section class="mb-5" id="forms" aria-labelledby="forms-title">
        <h2 class="h4" id="forms-title">Forms and confirmation</h2>
        <div class="card">
            <div class="card-body p-4">
                <form id="style-guide-form" method="GET" action="{{ route('style-guide') }}">
                    <div class="row">
                        <div class="col-md-6">
                            <x-ui.form.input name="sample_name" label="Event name" value="Community gathering" required help="Use a specific, recognizable name."/>
                        </div>
                        <div class="col-md-6">
                            <x-ui.form.select
                                name="sample_status"
                                label="Status"
                                :options="['draft' => 'Draft', 'planning' => 'Planning', 'completed' => 'Completed']"
                                value="planning"
                            />
                        </div>
                    </div>
                    <x-ui.form.textarea name="sample_notes" label="Notes" rows="3" help="Never place secrets or passwords in notes."/>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit">Primary action</button>
                        <button
                            class="btn btn-danger"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#confirmationModal"
                            data-confirm-form="style-guide-form"
                            data-confirm-title="Confirm example action?"
                            data-confirm-message="This demonstrates the shared confirmation dialog."
                            data-confirm-button="Confirm example"
                        >Destructive action</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="mb-5" id="data" aria-labelledby="data-title">
        <h2 class="h4" id="data-title">Filters, tables, empty states, and pagination</h2>
        <x-ui.filter-bar :action="route('style-guide')" :clear-url="route('style-guide')">
            <div class="col-12 col-md-6">
                <label class="form-label" for="sample-search">Search</label>
                <input class="form-control" id="sample-search" name="sample_search" placeholder="Search records">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="sample-filter-status">Status</label>
                <select class="form-select" id="sample-filter-status" name="sample_filter_status">
                    <option>All statuses</option>
                    <option>Active</option>
                    <option>Archived</option>
                </select>
            </div>
        </x-ui.filter-bar>

        <x-ui.data-table
            :columns="[
                ['label' => 'Record'],
                ['label' => 'Owner'],
                ['label' => 'Status'],
                ['label' => 'Actions'],
            ]"
            caption="Example records"
        >
            <tr>
                <td><strong>Sample record</strong><span class="d-block text-secondary">Reference EM-001</span></td>
                <td>Business Manager</td>
                <td><x-ui.status-badge status="active"/></td>
                <td><button class="btn btn-sm btn-outline-primary" type="button">View</button></td>
            </tr>
        </x-ui.data-table>
        <x-ui.pagination :paginator="$samplePagination"/>

        <div class="card mt-4">
            <div class="card-body">
                <x-ui.empty-state title="No records yet" description="When the first record is created, it will appear here.">
                    <x-slot:action><button class="btn btn-primary" type="button">Create first record</button></x-slot:action>
                </x-ui.empty-state>
            </div>
        </div>
    </section>
</x-layouts.app>
