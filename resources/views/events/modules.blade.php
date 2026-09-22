<x-layouts.app
    :title="'Modules · '.$event->name"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name, 'url' => route('events.show', $event)],
        ['label' => 'Modules'],
    ]"
>
    <x-ui.page-header title="Optional modules" :subtitle="$event->reference_number.' · Disabled-module data is preserved.'"/>
    <x-ui.validation-summary class="mb-4"/>
    @foreach ($warnings as $warning)<div class="alert alert-warning" role="status">{{ $warning }}</div>@endforeach

    <form method="POST" action="{{ route('events.modules.update', $event) }}">
        @csrf @method('PUT')
        <section class="card">
            <div class="card-body p-4">
                <p class="text-secondary">Dependencies are guidance only. All specialized modules can be disabled without blocking the Event lifecycle.</p>
                <div class="row g-3">
                    @foreach ($moduleDefinitions as $definition)
                        <div class="col-md-6">
                            <div class="form-check border rounded p-3 ps-5 h-100">
                                <input class="form-check-input" type="checkbox" name="enabled_modules[]" value="{{ $definition->key }}" id="module-{{ $definition->key }}" @checked(collect(old('enabled_modules', $enabledModules))->contains($definition->key)) @disabled(! $definition->is_active && ! in_array($definition->key, $enabledModules, true))>
                                <label class="form-check-label" for="module-{{ $definition->key }}">
                                    <span class="fw-semibold">{{ $definition->display_label }}</span>
                                    @unless ($definition->is_active)<span class="badge text-bg-secondary">Registry inactive</span>@endunless
                                    @php($setting = $event->moduleSettings->firstWhere('module_key', $definition->key))
                                    @if ($setting?->origin === 'template')
                                        <span class="badge text-bg-info">Template origin</span>
                                    @else
                                        <span class="badge text-bg-light border">Manual origin</span>
                                    @endif
                                    @if ($dataPresence[$definition->key] ?? false)
                                        <span class="badge text-bg-warning">Contains preserved data</span>
                                    @endif
                                    @if ($definition->description)<span class="d-block small text-secondary">{{ $definition->description }}</span>@endif
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" value="1" name="confirm_disable" id="confirm_disable" @checked(old('confirm_disable'))>
                    <label class="form-check-label" for="confirm_disable">I confirm any unchecked modules should be disabled. Existing module data will remain stored.</label>
                    @error('confirm_disable')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" name="confirm_disable_with_data" id="confirm_disable_with_data" @checked(old('confirm_disable_with_data'))>
                    <label class="form-check-label" for="confirm_disable_with_data">I explicitly confirm disabling any unchecked module that already contains data. Its records must remain stored.</label>
                    @error('confirm_disable_with_data')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="mt-3">
                    <x-ui.form.textarea name="reason" label="Change reason" :value="old('reason')" rows="3" help="Required when disabling a module that contains data; otherwise optional."/>
                </div>
            </div>
        </section>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary" type="submit">Save modules</button>
            <a class="btn btn-outline-secondary" href="{{ route('events.show', $event) }}">Cancel</a>
        </div>
    </form>

    <section class="card mt-5" aria-labelledby="module-history-title">
        <div class="card-body p-4">
            <h2 class="h4" id="module-history-title">Module change history</h2>
            <form class="row g-3 align-items-end mb-4" method="GET" action="{{ route('events.modules.edit', $event) }}">
                <div class="col-md-5">
                    <label class="form-label" for="history_module">Module</label>
                    <select class="form-select" id="history_module" name="history_module">
                        <option value="">All modules</option>
                        @foreach ($moduleDefinitions as $definition)
                            <option value="{{ $definition->key }}" @selected(($historyFilters['history_module'] ?? '') === $definition->key)>{{ $definition->display_label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="history_action">Action</label>
                    <select class="form-select" id="history_action" name="history_action">
                        <option value="">All actions</option>
                        <option value="enabled" @selected(($historyFilters['history_action'] ?? '') === 'enabled')>Enabled</option>
                        <option value="disabled" @selected(($historyFilters['history_action'] ?? '') === 'disabled')>Disabled</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-outline-primary" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="{{ route('events.modules.edit', $event) }}">Reset</a>
                </div>
            </form>

            <x-ui.data-table :columns="[['label' => 'Module'], ['label' => 'Change'], ['label' => 'Data'], ['label' => 'Actor'], ['label' => 'When'], ['label' => 'Reason']]" caption="Event module change history" :empty="$history->isEmpty()" empty-title="No module changes recorded">
                @foreach ($history as $change)
                    <tr>
                        <td>{{ $change->definition?->display_label ?? str($change->module_key)->headline() }}</td>
                        <td><x-ui.status-badge :status="$change->to_enabled ? 'enabled' : 'disabled'"/></td>
                        <td>{{ $change->had_data ? 'Preserved data present' : 'No registered data detected' }}</td>
                        <td>{{ $change->actor?->name ?? 'System' }}</td>
                        <td>{{ $change->changed_at->setTimezone($organizationTimezone)->format('Y-m-d H:i T') }}</td>
                        <td>{{ $change->reason ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
            <div class="mt-3">{{ $history->links() }}</div>
        </div>
    </section>
</x-layouts.app>
