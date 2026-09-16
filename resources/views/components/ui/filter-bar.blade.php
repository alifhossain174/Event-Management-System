@props(['action', 'clearUrl' => null])

<form method="GET" action="{{ $action }}" {{ $attributes->merge(['class' => 'filter-bar card card-body mb-4']) }}>
    <div class="row g-3 align-items-end">
        {{ $slot }}
        <div class="col-12 col-lg-auto ms-lg-auto">
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">
                    <x-ui.icon name="filter" :size="17" class="me-1"/>Apply filters
                </button>
                @if ($clearUrl)
                    <a class="btn btn-outline-secondary" href="{{ $clearUrl }}">Clear</a>
                @endif
            </div>
        </div>
    </div>
</form>
