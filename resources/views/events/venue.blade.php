<x-layouts.app :title="'Venue · '.$event->name" wide :breadcrumbs="[['label'=>'Events','url'=>route('events.index')],['label'=>$event->name,'url'=>route('events.show',$event)],['label'=>'Venue']]">
    <x-ui.page-header title="Venue workspace" :subtitle="$event->reference_number.' · '.$event->name"><x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('events.show',$event) }}">Back to event</a><a class="btn btn-outline-primary" href="{{ route('venues.availability') }}">Global availability</a></x-slot:actions></x-ui.page-header>
    <p class="visually-hidden">Module workspace ready</p>
    <x-ui.validation-summary class="mb-4"/>
    <div class="row g-4"><div class="col-xl-8">
        <section class="card"><div class="card-body p-4"><h2 class="h4">Allocations</h2>
            <x-ui.data-table :columns="[['label'=>'Venue / space'],['label'=>'Use'],['label'=>'Price'],['label'=>'Warnings'],['label'=>'Action']]" caption="Event venue allocations" :empty="$event->venueAllocations->isEmpty()" empty-title="No venue allocated">
                @foreach($event->venueAllocations as $allocation)<tr><td><a href="{{ route('venues.show',$allocation->venue) }}">{{ $allocation->venue->name }}</a><span class="d-block small text-secondary">{{ $allocation->space?->name ?? 'Whole venue' }}</span></td><td><x-ui.status-badge :status="$allocation->status"/> {{ $allocation->is_exclusive ? 'Exclusive' : 'Shared' }}</td><td>{{ $allocation->quoted_price !== null ? $allocation->currency_code.' '.number_format((float)$allocation->quoted_price,2) : 'Quote not recorded' }}</td><td>@if($allocation->capacity_warning)<span class="badge text-bg-warning">Over capacity</span>@endif @if($allocation->conflict_override)<span class="badge text-bg-danger">Conflict overridden</span><span class="d-block small">{{ $allocation->conflict_override_reason }}</span>@endif</td><td>@if($allocation->status!=='cancelled' && auth()->user()->hasPermission('venues.allocate'))<form method="POST" action="{{ route('events.venue.allocations.cancel',[$event,$allocation]) }}">@csrf @method('PATCH')<input class="form-control form-control-sm mb-2" name="reason" aria-label="Cancellation reason" placeholder="Cancellation reason" required><button class="btn btn-sm btn-outline-danger">Cancel</button></form>@endif</td></tr>@endforeach
            </x-ui.data-table>
        </div></section>
    </div><aside class="col-xl-4">
        @if(auth()->user()->hasPermission('venues.allocate'))<section class="card"><div class="card-body p-4"><h2 class="h4">Allocate venue</h2><p class="small text-secondary">Availability uses {{ $event->starts_at->format('M j, Y H:i') }} to {{ $event->ends_at->format('M j, Y H:i') }} {{ $event->timezone }}. Adjacent allocations do not overlap.</p>
            <form method="POST" action="{{ route('events.venue.allocations.store',$event) }}">@csrf
                <x-ui.form.select name="venue_id" label="Venue" :options="$venues->pluck('name','id')" required placeholder="Select venue"/>
                <label class="form-label" for="venue_space_id">Space (optional)</label><select class="form-select mb-3" id="venue_space_id" name="venue_space_id"><option value="">Whole venue</option>@foreach($venues as $venue)@foreach($venue->spaces as $space)<option value="{{ $space->id }}">{{ $venue->name }} — {{ $space->name }} ({{ $space->capacity ?: 'capacity not set' }})</option>@endforeach @endforeach</select>
                <x-ui.form.select name="status" label="Allocation status" :options="['planned'=>'Planned','confirmed'=>'Confirmed']" value="planned" required/>
                <input type="hidden" name="is_exclusive" value="0"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="allocation-exclusive" name="is_exclusive" value="1" checked><label class="form-check-label" for="allocation-exclusive">Exclusive allocation</label></div>
                <div class="row"><div class="col-8"><x-ui.form.input name="quoted_price" label="Quoted price" type="number" min="0" step="0.0001"/></div><div class="col-4"><x-ui.form.input name="currency_code" label="Currency" maxlength="3" :value="$event->currency_code"/></div></div>
                <x-ui.form.input name="rate_type_snapshot" label="Rate description" placeholder="Optional negotiated basis"/>
                <x-ui.form.textarea name="notes" label="Allocation notes" rows="2"/>
                <input type="hidden" name="override_conflict" value="0"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="override-conflict" name="override_conflict" value="1"><label class="form-check-label" for="override-conflict">Request conflict override</label></div>
                <x-ui.form.textarea name="override_reason" label="Override reason" rows="2"/>
                <button class="btn btn-primary">Check and allocate</button>
            </form>
        </div></section>@endif
    </aside></div>
</x-layouts.app>
