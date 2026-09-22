<x-layouts.app title="Search" wide :breadcrumbs="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Search']]">
    <x-ui.page-header title="Global search" subtitle="Search only the records your account is authorized to view."/>

    <form class="card card-body mb-4" method="GET" action="{{ route('search') }}" role="search">
        <label class="form-label fw-semibold" for="search-query">Search term</label>
        <div class="input-group">
            <span class="input-group-text"><x-ui.icon name="search" :size="18"/></span>
            <input
                class="form-control"
                id="search-query"
                name="q"
                value="{{ $term }}"
                placeholder="Booking, event, client, vendor, or user"
                minlength="{{ $minimumLength }}"
                maxlength="100"
                autofocus
            >
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
        <p class="form-text mb-0">Enter at least {{ $minimumLength }} characters. Results are limited per record type.</p>
    </form>

    @if (mb_strlen($term) < $minimumLength)
        <x-ui.empty-state title="Start a search" description="Enter at least two characters to search available records."/>
    @elseif ($groups->isEmpty())
        <x-ui.empty-state title="No permitted results" description="No records you may view match this search."/>
    @else
        <div class="row g-4">
            @foreach ($groups as $group)
                <section class="col-12 col-xl-6" aria-labelledby="search-group-{{ $group['key'] }}">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <h2 class="h5 mb-0" id="search-group-{{ $group['key'] }}">{{ $group['label'] }}</h2>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($group['results'] as $result)
                                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-3 p-3" href="{{ $result->url }}">
                                    <span>
                                        <strong class="d-block">{{ $result->title }}</strong>
                                        @if ($result->subtitle)<span class="small text-secondary">{{ $result->subtitle }}</span>@endif
                                    </span>
                                    @if ($result->status)<x-ui.status-badge :status="$result->status"/>@endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
