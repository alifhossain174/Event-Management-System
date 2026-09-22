@php
    $editing = isset($event);
    $eventTimezone = old('timezone', $event->timezone ?? $organizationTimezone);
    $startValue = $editing ? $event->starts_at->setTimezone($eventTimezone)->format('Y-m-d\TH:i') : null;
    $endValue = $editing ? $event->ends_at->setTimezone($eventTimezone)->format('Y-m-d\TH:i') : null;
    $templateDefaults = $templates->mapWithKeys(fn ($template) => [
        (string) $template->id => $template->modules->where('recommendation', 'default')->pluck('module_key')->values(),
    ]);
    $selectedModules = collect(old('enabled_modules', $initialEnabledModules ?? []));
@endphp

<x-ui.validation-summary class="mb-4"/>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="card mb-4">
            <div class="card-body p-4">
                <h2 class="h4">Core details</h2>
                <div class="row g-3">
                    <div class="col-12"><x-ui.form.input name="name" label="Event name" :value="$event->name ?? null" required maxlength="180"/></div>
                    <div class="col-md-6">
                        <x-clients.selector name="client_id" label="Client" :clients="$clients" :value="$event->client_id ?? null" :required="false"/>
                        <div class="form-text">A Client may be omitted in Draft, but is required before confirmation.</div>
                    </div>
                    <div class="col-md-6">
                        <x-ui.form.select name="event_category_id" label="Event category" :options="$categories->pluck('name', 'id')" :value="$event->event_category_id ?? null" placeholder="Select category" required/>
                    </div>
                    @unless ($editing)
                        <div class="col-md-6">
                            <x-ui.form.select name="event_template_id" label="Template" :options="$templates->pluck('name', 'id')" :value="$selectedTemplate?->id" placeholder="No template / Custom" data-event-template/>
                            <div class="form-text">Templates suggest a starting configuration; the checklist remains editable.</div>
                        </div>
                    @endunless
                    <div class="col-md-6"><x-ui.form.select name="manager_user_id" label="Organizer / manager" :options="$managers->pluck('name', 'id')" :value="$event->manager_user_id ?? null" placeholder="Unassigned"/></div>
                    <div class="col-md-6"><x-ui.form.select name="branch_id" label="Branch" :options="$branches->pluck('name', 'id')" :value="$event->branch_id ?? null" placeholder="Unscoped / default branch"/></div>
                </div>
            </div>
        </section>

        <section class="card mb-4">
            <div class="card-body p-4">
                <h2 class="h4">Schedule</h2>
                <div class="row g-3">
                    <div class="col-md-6"><x-ui.form.input name="starts_at_local" label="Starts" type="datetime-local" :value="$startValue" required/></div>
                    <div class="col-md-6"><x-ui.form.input name="ends_at_local" label="Ends" type="datetime-local" :value="$endValue" required/></div>
                    <div class="col-md-6"><x-ui.form.select name="timezone" label="Event time zone" :options="$timezones" :value="$eventTimezone" required/></div>
                </div>
                <p class="form-text mb-0">Times are entered in the selected event time zone and stored consistently in UTC.</p>
            </div>
        </section>

        <section class="card mb-4">
            <div class="card-body p-4">
                <h2 class="h4">Planning brief</h2>
                <div class="row g-3">
                    <div class="col-md-6"><x-ui.form.input name="expected_guest_count" label="Expected guests" type="number" :value="$event->expected_guest_count ?? null" min="0"/></div>
                    <div class="col-md-6"><x-ui.form.input name="core_budget_estimate" label="Core budget estimate" type="number" :value="$event->core_budget_estimate ?? null" min="0" step="0.0001"/></div>
                    <div class="col-md-6"><x-ui.form.input name="theme" label="Theme" :value="$event->theme ?? null"/></div>
                    <div class="col-md-6"><x-ui.form.input name="dress_code" label="Dress code" :value="$event->dress_code ?? null"/></div>
                    <div class="col-12"><x-ui.form.textarea name="description" label="Description" :value="$event->description ?? null" rows="6"/></div>
                </div>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="card mb-4">
            <div class="card-body p-4">
                <h2 class="h4">Primary event contact</h2>
                <x-ui.form.input name="primary_contact_name" label="Name" :value="$event->primary_contact_name ?? null"/>
                <x-ui.form.input name="primary_contact_email" label="Email" type="email" :value="$event->primary_contact_email ?? null"/>
                <x-ui.form.input name="primary_contact_phone" label="Phone" type="tel" :value="$event->primary_contact_phone ?? null"/>
            </div>
        </section>

        @unless ($editing)
            <section class="card" aria-labelledby="event-modules-title">
                <div class="card-body p-4">
                    <h2 class="h4" id="event-modules-title">Optional modules</h2>
                    <p class="text-secondary">All specialized modules may remain disabled. A template only checks suggestions.</p>
                    @error('enabled_modules')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    <div class="vstack gap-2" data-event-modules>
                        @foreach ($moduleDefinitions as $definition)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enabled_modules[]" value="{{ $definition->key }}" id="event-module-{{ $definition->key }}" @checked($selectedModules->contains($definition->key))>
                                <label class="form-check-label" for="event-module-{{ $definition->key }}">
                                    <span class="fw-semibold">{{ $definition->display_label }}</span>
                                    @if ($definition->description)<span class="d-block small text-secondary">{{ $definition->description }}</span>@endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endunless
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create draft event' }}</button>
    <a class="btn btn-outline-secondary" href="{{ $editing ? route('events.show', $event) : route('events.index') }}">Cancel</a>
</div>

@unless ($editing)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const template = document.querySelector('[data-event-template]');
            const moduleRoot = document.querySelector('[data-event-modules]');
            const defaults = @js($templateDefaults);
            template?.addEventListener('change', () => {
                const enabled = new Set(defaults[template.value] ?? []);
                moduleRoot?.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                    checkbox.checked = enabled.has(checkbox.value);
                });
            });
        });
    </script>
@endunless
