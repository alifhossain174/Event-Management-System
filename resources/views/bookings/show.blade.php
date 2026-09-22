<x-layouts.app title="{{ $booking->reference_number }}" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Bookings', 'url' => route('bookings.index')], ['label' => $booking->reference_number]]">
    <x-ui.page-header :title="$booking->reference_number" :subtitle="$booking->client->display_name.' · '.$booking->category->name">
        <x-slot:actions><x-ui.status-badge :status="$booking->status"/> @can('convert', $booking)<a class="btn btn-primary" href="{{ route('bookings.convert.create', $booking) }}">{{ $booking->event_id ? 'Open event' : 'Convert to event' }}</a>@endcan</x-slot:actions>
    </x-ui.page-header>
    <x-ui.validation-summary class="mb-4"/>
    @if ($booking->financial_review_required)<div class="alert alert-warning"><strong>Financial review required.</strong> Related financial records were flagged and preserved when this Booking was cancelled.</div>@endif

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Request summary</h2><dl class="row mb-0">
                <dt class="col-sm-4">Client</dt><dd class="col-sm-8"><a href="{{ route('clients.show', $booking->client) }}">{{ $booking->client->display_name }}</a></dd>
                <dt class="col-sm-4">Requested schedule</dt><dd class="col-sm-8">{{ $booking->requested_starts_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }} – {{ $booking->requested_ends_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }} {{ $organizationTimezone }}</dd>
                <dt class="col-sm-4">Venue preference</dt><dd class="col-sm-8">{{ $booking->venue_preference ?: 'Not specified' }}</dd>
                <dt class="col-sm-4">Expected guests</dt><dd class="col-sm-8">{{ $booking->expected_guest_count === null ? 'Not specified' : number_format($booking->expected_guest_count) }}</dd>
                <dt class="col-sm-4">Budget estimate</dt><dd class="col-sm-8">{{ $booking->budget_estimate === null ? 'Not specified' : $booking->currency_code.' '.number_format((float) $booking->budget_estimate, 2) }}</dd>
                <dt class="col-sm-4">Linked Event</dt><dd class="col-sm-8">@if ($booking->event)<a href="{{ route('events.show', $booking->event) }}">{{ $booking->event->reference_number }} · {{ $booking->event->name }}</a>@else Not converted @endif</dd>
                <dt class="col-sm-4">Notes</dt><dd class="col-sm-8">{!! nl2br(e($booking->notes ?: 'No notes')) !!}</dd>
            </dl></div></section>

            <section class="card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Status history</h2></div><div class="card-body p-0"><div class="list-group list-group-flush">
                @foreach ($booking->statusHistory as $history)<div class="list-group-item"><div class="d-flex justify-content-between gap-3"><span><x-ui.status-badge :status="$history->to_status"/> <span class="ms-2">{{ $history->reason }}</span></span><small class="text-secondary">{{ $history->changed_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }}</small></div><small class="text-secondary">{{ $history->actor?->name ?? 'System' }}</small></div>@endforeach
            </div></div></section>

            @if ($booking->changes->isNotEmpty())<section class="card"><div class="card-header bg-white"><h2 class="h5 mb-0">Change history</h2></div><div class="card-body p-0"><div class="list-group list-group-flush">@foreach ($booking->changes as $change)<div class="list-group-item"><strong>{{ str($change->type)->headline() }}</strong> · {{ $change->reason }}<span class="d-block small text-secondary">{{ $change->actor?->name ?? 'System' }} · {{ $change->occurred_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }}@if ($change->conflict_override) · conflict override recorded @endif</span></div>@endforeach</div></div></section>@endif
        </div>

        <div class="col-xl-4">
            @can('review', $booking)<form class="card mb-3" method="POST" action="{{ route('bookings.review', $booking) }}">@csrf<div class="card-body"><h2 class="h5">Start review</h2><x-ui.form.textarea name="reason" label="Review note" rows="2"/><button class="btn btn-outline-primary" type="submit">Move to review</button></div></form>@endcan
            @can('confirm', $booking)<form class="card mb-3" method="POST" action="{{ route('bookings.confirm', $booking) }}">@csrf<div class="card-body"><h2 class="h5">Approve and confirm</h2><p class="small text-secondary">The default workflow confirms in one manager action.</p><x-ui.form.textarea name="reason" label="Approval note" rows="2"/><button class="btn btn-success" type="submit">Approve booking</button></div></form>@endcan
            @can('waitlist', $booking)<form class="card mb-3" method="POST" action="{{ route('bookings.waitlist', $booking) }}">@csrf<div class="card-body"><h2 class="h5">Waitlist</h2><x-ui.form.textarea name="reason" label="Reason" rows="2"/><button class="btn btn-outline-warning" type="submit">Add to waitlist</button></div></form>@endcan
            @can('reschedule', $booking)<form class="card mb-3" method="POST" action="{{ route('bookings.reschedule', $booking) }}">@csrf @method('PATCH')<div class="card-body"><h2 class="h5">Reschedule</h2><x-ui.form.input name="requested_starts_at_local" label="New start" type="datetime-local" :value="$booking->requested_starts_at->setTimezone($booking->timezone)->format('Y-m-d\TH:i')" required/><x-ui.form.input name="requested_ends_at_local" label="New end" type="datetime-local" :value="$booking->requested_ends_at->setTimezone($booking->timezone)->format('Y-m-d\TH:i')" required/><x-ui.form.select name="timezone" label="Time zone" :options="$timezones" :value="$booking->timezone" required/><x-ui.form.input name="venue_preference" label="Venue preference" :value="$booking->venue_preference"/><x-ui.form.textarea name="reason" label="Reason" rows="2" required/>@can('bookings.override-conflicts')<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="override_conflicts" value="1" id="override-conflicts"><label class="form-check-label" for="override-conflicts">Override reported resource conflicts</label></div>@endcan<button class="btn btn-outline-primary" type="submit">Save schedule</button></div></form>@endcan
            @can('cancel', $booking)<form class="card border-danger" method="POST" action="{{ route('bookings.cancel', $booking) }}">@csrf<div class="card-body"><h2 class="h5">Cancel booking</h2><x-ui.form.textarea name="reason" label="Cancellation reason" rows="3" required/><button class="btn btn-outline-danger" type="submit">Cancel booking</button></div></form>@endcan
        </div>
    </div>
</x-layouts.app>
