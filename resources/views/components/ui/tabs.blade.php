@props(['items'])

<nav aria-label="Section navigation">
    <ul {{ $attributes->merge(['class' => 'nav nav-tabs']) }}>
        @foreach ($items as $item)
            <li class="nav-item">
                <a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
