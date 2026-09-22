@props(['groups'])

<div class="sidebar-brand">
    <a href="{{ route('home') }}" class="d-flex align-items-center gap-2 text-decoration-none text-white">
        <span class="sidebar-brand-mark" aria-hidden="true">EM</span>
        <span>
            <strong class="d-block">Event Management</strong>
            <small>Operations workspace</small>
        </span>
    </a>
</div>

<nav class="sidebar-navigation" aria-label="Application navigation">
    @foreach ($groups as $group)
        <div class="sidebar-group">
            <p class="sidebar-group-label">{{ $group['label'] }}</p>
            <ul class="nav nav-pills flex-column gap-1">
                @foreach ($group['items'] as $item)
                    @php($isActive = request()->routeIs(...$item['patterns']))
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-3 {{ $isActive ? 'active' : '' }}"
                           href="{{ route($item['route'], $item['parameters'] ?? []) }}"
                           @if ($isActive) aria-current="page" @endif>
                            <x-ui.icon :name="$item['icon']" :size="19"/>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
