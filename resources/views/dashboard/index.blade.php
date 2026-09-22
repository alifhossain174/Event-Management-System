<x-layouts.app title="Dashboard" wide :breadcrumbs="[['label' => 'Dashboard']]">
    <x-ui.page-header title="Dashboard" subtitle="Live operational visibility derived from records you are authorized to view."/>

    @if ($canViewEvents)
        <x-ui.filter-bar :action="route('dashboard')" :clear-url="route('dashboard')">
            <div class="col-12 col-sm-6 col-xl-2">
                <label class="form-label" for="date_from">Starts from</label>
                <input class="form-control" id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-12 col-sm-6 col-xl-2">
                <label class="form-label" for="date_to">Starts through</label>
                <input class="form-control" id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <label class="form-label" for="category">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($branches->isNotEmpty())
                <div class="col-12 col-sm-6 col-xl-2">
                    <label class="form-label" for="branch">Branch</label>
                    <select class="form-select" id="branch" name="branch">
                        <option value="">All permitted branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($managers->isNotEmpty())
                <div class="col-12 col-sm-6 col-xl-3">
                    <label class="form-label" for="manager">Manager</label>
                    <select class="form-select" id="manager" name="manager">
                        <option value="">All visible managers</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((string) ($filters['manager'] ?? '') === (string) $manager->id)>{{ $manager->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </x-ui.filter-bar>

        <section aria-labelledby="event-metrics-heading" class="mb-4">
            <h2 class="visually-hidden" id="event-metrics-heading">Event metrics</h2>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3">
                @foreach ($metrics as $metric)
                    <div class="col">
                        <a class="card h-100 text-decoration-none text-body shadow-sm" href="{{ $metric['url'] }}" aria-label="View {{ strtolower($metric['label']) }}: {{ $metric['count'] }}">
                            <div class="card-body">
                                <span class="d-block small text-secondary mb-2">{{ $metric['label'] }}</span>
                                <strong class="display-6">{{ number_format($metric['count']) }}</strong>
                                <span class="d-block small text-primary mt-2">View filtered events</span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card" aria-labelledby="upcoming-events-heading">
            <div class="card-header bg-white d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h2 class="h5 mb-1" id="upcoming-events-heading">Next upcoming events</h2>
                    <p class="small text-secondary mb-0">Times shown in {{ $organizationTimezone }}.</p>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('events.index', array_filter($filters) + ['view' => 'upcoming']) }}">View all</a>
            </div>
            <div class="card-body p-0">
                @if ($upcomingEvents->isEmpty())
                    <x-ui.empty-state title="No upcoming events" description="No permitted event matches the selected filters."/>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($upcomingEvents as $event)
                            <a class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between gap-2 p-3" href="{{ route('events.show', $event) }}">
                                <span>
                                    <strong class="d-block">{{ $event->name }}</strong>
                                    <span class="small text-secondary">{{ $event->reference_number }} · {{ $event->client?->display_name ?? 'No client' }}</span>
                                </span>
                                <span class="text-md-end">
                                    <span class="d-block">{{ $event->starts_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }}</span>
                                    <x-ui.status-badge :status="$event->status"/>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @else
        <x-ui.empty-state
            title="Your workspace is ready"
            description="No dashboard data source is available to your role yet. Use the permitted navigation items to continue."
        />
    @endif

    @if ($canViewBookings)
        <section class="card mt-4" aria-labelledby="latest-bookings-heading">
            <div class="card-header bg-white d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h2 class="h5 mb-1" id="latest-bookings-heading">Latest booking enquiries</h2>
                    <p class="small text-secondary mb-0">Optional upstream requests matching the selected date, branch, and category filters.</p>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('bookings.index', array_filter(collect($filters)->only(['date_from', 'date_to', 'branch', 'category'])->all())) }}">View all</a>
            </div>
            <div class="card-body p-0">
                @if ($latestBookings->isEmpty())
                    <x-ui.empty-state title="No booking enquiries" description="Direct Event creation remains available without a Booking."/>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($latestBookings as $booking)
                            <a class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between gap-2 p-3" href="{{ route('bookings.show', $booking) }}">
                                <span>
                                    <strong class="d-block">{{ $booking->reference_number }}</strong>
                                    <span class="small text-secondary">{{ $booking->client?->display_name }} · {{ $booking->category?->name }}</span>
                                </span>
                                <span class="text-md-end">
                                    <span class="d-block">{{ $booking->requested_starts_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }}</span>
                                    <x-ui.status-badge :status="$booking->status"/>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif
</x-layouts.app>
