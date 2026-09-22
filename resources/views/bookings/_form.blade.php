@php($bookingTimezone = old('timezone', $organizationTimezone))
<x-ui.validation-summary class="mb-4"/>
<div class="row g-4">
    <div class="col-xl-8">
        <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Enquiry details</h2><div class="row g-3">
            <div class="col-md-6"><x-clients.selector name="client_id" label="Client" :clients="$clients" :value="null" required/></div>
            <div class="col-md-6"><x-ui.form.select name="requested_event_category_id" label="Requested event category" :options="$categories->pluck('name', 'id')" placeholder="Select category" required/></div>
            @if ($branches->isNotEmpty())<div class="col-md-6"><x-ui.form.select name="branch_id" label="Branch" :options="$branches->pluck('name', 'id')" placeholder="Unscoped / default branch"/></div>@endif
            <div class="col-md-6"><x-ui.form.input name="venue_preference" label="Venue preference" maxlength="255"/></div>
        </div></div></section>
        <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Requested schedule</h2><div class="row g-3">
            <div class="col-md-6"><x-ui.form.input name="requested_starts_at_local" label="Starts" type="datetime-local" required/></div>
            <div class="col-md-6"><x-ui.form.input name="requested_ends_at_local" label="Ends" type="datetime-local" required/></div>
            <div class="col-md-6"><x-ui.form.select name="timezone" label="Time zone" :options="$timezones" :value="$bookingTimezone" required/></div>
        </div><p class="form-text mb-0">Times are stored in UTC and displayed in the organization time zone.</p></div></section>
        <section class="card"><div class="card-body p-4"><h2 class="h4">Planning request</h2><div class="row g-3">
            <div class="col-md-6"><x-ui.form.input name="expected_guest_count" label="Expected guests" type="number" min="0"/></div>
            <div class="col-md-6"><x-ui.form.input name="budget_estimate" label="Budget estimate" type="number" min="0" step="0.0001"/></div>
            <div class="col-12"><x-ui.form.textarea name="notes" label="Notes" rows="6"/></div>
        </div></div></section>
    </div>
    <div class="col-xl-4"><div class="alert alert-info"><strong>Optional workflow</strong><p class="mb-0 mt-2">This enquiry does not reserve resources and does not create an Event. A manager may approve it and convert it later.</p></div></div>
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Create enquiry</button><a class="btn btn-outline-secondary" href="{{ route('bookings.index') }}">Cancel</a></div>
