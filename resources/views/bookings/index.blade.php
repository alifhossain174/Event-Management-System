<x-layouts.app title="Bookings" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Bookings']]">
    <x-ui.page-header title="Bookings" subtitle="Optional enquiry and approval records; Events can still be created directly.">
        <x-slot:actions>@can('create', App\Models\Booking::class)<a class="btn btn-primary" href="{{ route('bookings.create') }}">Create enquiry</a>@endcan</x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('bookings.index')" :clear-url="route('bookings.index')">
        <div class="col-12 col-xl-4"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, venue, or client"></div>
        <div class="col-12 col-sm-6 col-xl-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option>@foreach (App\Models\Booking::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></div>
        <div class="col-12 col-sm-6 col-xl-2"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="category"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-12 col-sm-6 col-xl-2"><label class="form-label" for="client">Client</label><select class="form-select" id="client" name="client"><option value="">All clients</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected((string) ($filters['client'] ?? '') === (string) $client->id)>{{ $client->display_name }}</option>@endforeach</select></div>
        @if ($branches->isNotEmpty())<div class="col-12 col-sm-6 col-xl-2"><label class="form-label" for="branch">Branch</label><select class="form-select" id="branch" name="branch"><option value="">All branches</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) ($filters['branch'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@endif
        <div class="col-12 col-sm-6 col-xl-2"><label class="form-label" for="date_from">Starts from</label><input class="form-control" id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div class="col-12 col-sm-6 col-xl-2"><label class="form-label" for="date_to">Starts through</label><input class="form-control" id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
    </x-ui.filter-bar>

    <x-ui.data-table :columns="[['label' => 'Booking'], ['label' => 'Client'], ['label' => 'Requested schedule'], ['label' => 'Category'], ['label' => 'Status'], ['label' => 'Actions', 'class' => 'table-actions']]" caption="Booking enquiries" :empty="$bookings->isEmpty()" empty-title="No bookings match these filters" empty-description="Create an optional enquiry or continue with direct Event creation.">
        @foreach ($bookings as $booking)
            <tr>
                <td><a class="fw-semibold text-decoration-none" href="{{ route('bookings.show', $booking) }}">{{ $booking->reference_number }}</a><span class="d-block small text-secondary">{{ $booking->venue_preference ?: 'Venue not specified' }}</span></td>
                <td>{{ $booking->client->display_name }}</td>
                <td>{{ $booking->requested_starts_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }}<span class="d-block small text-secondary">{{ $organizationTimezone }}</span></td>
                <td>{{ $booking->category->name }}</td>
                <td><x-ui.status-badge :status="$booking->status"/></td>
                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('bookings.show', $booking) }}">Review</a></td>
            </tr>
        @endforeach
    </x-ui.data-table>
    <x-ui.pagination :paginator="$bookings"/>
</x-layouts.app>
