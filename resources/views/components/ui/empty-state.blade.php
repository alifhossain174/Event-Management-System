@props(['title', 'description' => null, 'icon' => 'inbox'])

<div {{ $attributes->merge(['class' => 'empty-state text-center']) }}>
    <span class="empty-state-icon" aria-hidden="true"><x-ui.icon :name="$icon" :size="28"/></span>
    <h2 class="h5 mt-3 mb-2">{{ $title }}</h2>
    @if ($description)
        <p class="text-secondary mb-3">{{ $description }}</p>
    @endif
    @if (isset($action))
        <div>{{ $action }}</div>
    @endif
</div>
