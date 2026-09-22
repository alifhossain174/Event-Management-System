@props(['title', 'subtitle' => null])

<header {{ $attributes->merge(['class' => 'page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4']) }}>
    <div>
        <h1 class="h2 mb-1">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-secondary mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endif
</header>
