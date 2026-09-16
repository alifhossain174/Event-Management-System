@props(['name', 'size' => 20, 'label' => null])

<svg
    {{ $attributes->merge(['class' => 'ui-icon']) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
    focusable="false"
>
    @switch($name)
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16"/>
            @break
        @case('home')
            <path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            @break
        @case('palette')
            <path d="M12 3a9 9 0 0 0 0 18h1.5a1.5 1.5 0 0 0 0-3H12a1.5 1.5 0 0 1 0-3h2a7 7 0 0 0 0-14Z"/><circle cx="7.5" cy="10.5" r=".75" fill="currentColor"/><circle cx="9.5" cy="6.5" r=".75" fill="currentColor"/><circle cx="14.5" cy="6.5" r=".75" fill="currentColor"/><circle cx="16.5" cy="10.5" r=".75" fill="currentColor"/>
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('check')
            <path d="m5 12 4 4L19 6"/>
            @break
        @case('alert')
            <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>
            @break
        @case('x')
            <path d="m6 6 12 12M18 6 6 18"/>
            @break
        @case('inbox')
            <path d="M4 4h16v16H4z"/><path d="M4 13h4l2 3h4l2-3h4"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>
            @break
        @case('filter')
            <path d="M4 5h16M7 12h10M10 19h4"/>
            @break
        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            @break
        @case('arrow-left')
            <path d="m15 18-6-6 6-6"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
