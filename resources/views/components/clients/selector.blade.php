@props([
    'name' => 'client_id',
    'label' => 'Client',
    'clients' => collect(),
    'value' => null,
    'required' => true,
    'allowCreate' => true,
])

@php($componentId = 'client-selector-'.Str::random(8))
<div id="{{ $componentId }}" data-client-selector>
    <div class="d-flex justify-content-between align-items-end gap-3">
        <div class="flex-grow-1">
            <x-ui.form.select
                :name="$name"
                :label="$label"
                :options="$clients->pluck('display_name', 'id')"
                :value="$value"
                placeholder="Select a client"
                :required="$required"
                data-client-select
            />
        </div>
        @if ($allowCreate && auth()->user()?->can('create', App\Models\Client::class))
            <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $componentId }}-modal">Quick create</button>
        @endif
    </div>

    @if ($allowCreate && auth()->user()?->can('create', App\Models\Client::class))
        <div class="modal fade" id="{{ $componentId }}-modal" tabindex="-1" aria-labelledby="{{ $componentId }}-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form data-client-quick-form action="{{ route('clients.quick-create') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="{{ $componentId }}-title">Create client</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" data-client-errors role="alert"></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Type</label><select class="form-select" name="type" required><option value="individual">Individual</option><option value="organization">Organization</option></select></div>
                                <div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name"></div>
                                <div class="col-12"><label class="form-label">Organization name</label><input class="form-control" name="organization_name"></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="primary_email"></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" type="tel" name="primary_phone"></div>
                            </div>
                            <p class="form-text mb-0 mt-3">The same validation and duplicate-warning service used by the full client screen is applied here.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create and select</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const root = document.getElementById(@js($componentId));
                const form = root?.querySelector('[data-client-quick-form]');
                if (!form) return;
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const errors = root.querySelector('[data-client-errors]');
                    errors.classList.add('d-none');
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value},
                        body: new FormData(form),
                    });
                    const body = await response.json();
                    if (!response.ok) {
                        errors.textContent = Object.values(body.errors ?? {client: ['Unable to create client.']}).flat().join(' ');
                        errors.classList.remove('d-none');
                        return;
                    }
                    const select = root.querySelector('[data-client-select]');
                    select.add(new Option(body.data.text, body.data.id, true, true));
                    bootstrap.Modal.getOrCreateInstance(root.querySelector('.modal')).hide();
                    form.reset();
                });
            });
        </script>
    @endif
</div>
