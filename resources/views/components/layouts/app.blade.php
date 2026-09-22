@props(['title' => null, 'breadcrumbs' => [], 'wide' => false])

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">

    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ auth()->check() ? 'admin-body' : 'guest-body' }}">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    @auth
        <div class="admin-shell">
            <aside class="admin-sidebar d-none d-lg-flex flex-column">
                <x-navigation.sidebar :groups="$navigationGroups ?? []"/>
            </aside>

            <div class="offcanvas offcanvas-start admin-mobile-sidebar" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
                <div class="offcanvas-header border-bottom border-light border-opacity-10">
                    <h2 class="offcanvas-title h5 mb-0 text-white" id="mobileSidebarLabel">Navigation</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
                </div>
                <div class="offcanvas-body p-0">
                    <x-navigation.sidebar :groups="$navigationGroups ?? []"/>
                </div>
            </div>

            <div class="admin-main">
                <header class="admin-topbar">
                    <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation">
                        <x-ui.icon name="menu"/>
                    </button>

                    <div class="ms-auto d-flex align-items-center gap-2">
                        @can('dashboard.view')
                            <form class="d-none d-md-flex" method="GET" action="{{ route('search') }}" role="search">
                                <label class="visually-hidden" for="global-search">Search bookings, events, clients, vendors, invoices, and users</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><x-ui.icon name="search" :size="16"/></span>
                                    <input
                                        class="form-control"
                                        id="global-search"
                                        name="q"
                                        value="{{ request()->routeIs('search') ? request('q') : '' }}"
                                        placeholder="Global search"
                                        maxlength="100"
                                    >
                                </div>
                            </form>
                        @endcan

                        @can('notifications.view')
                            <div class="dropdown">
                                <button class="btn btn-icon position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications, {{ ($notificationUnreadCount ?? 0) > 0 ? ($notificationUnreadCount.' unread') : 'none unread' }}">
                                    <x-ui.icon name="bell"/>
                                    <span class="notification-indicator" aria-hidden="true">{{ min(99, $notificationUnreadCount ?? 0) }}</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end notification-menu p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2"><p class="fw-semibold mb-0">Notifications</p><a class="small" href="{{ route('notifications.index') }}">View all</a></div>
                                    @forelse(($recentNotifications ?? collect()) as $recipient)
                                        <form method="POST" action="{{ route('notifications.read', $recipient) }}" class="border-top py-2">@csrf @method('PATCH')<button class="btn btn-link text-start text-decoration-none p-0 w-100" type="submit"><span class="d-block small fw-semibold text-body">{{ $recipient->notification->title }}</span><span class="d-block small text-secondary">{{ Str::limit($recipient->notification->body, 80) }}</span></button></form>
                                    @empty
                                        <p class="small text-secondary mb-0">No notifications.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endcan

                        <div class="dropdown">
                            <button class="btn account-menu-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="account-avatar" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="d-none d-sm-block text-start">
                                    <strong class="d-block small">{{ auth()->user()->name }}</strong>
                                    <span class="d-block text-secondary account-role">{{ auth()->user()->roles->pluck('name')->first() ?? 'User' }}</span>
                                </span>
                                <x-ui.icon name="chevron-down" :size="15"/>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('profile.edit') }}"><x-ui.icon name="user" :size="17"/>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="dropdown-item" type="submit">Log out</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </header>

                <main id="main-content" class="admin-content" tabindex="-1">
                    <div class="{{ $wide ? 'container-fluid' : 'container-xl' }}">
                        <x-ui.breadcrumbs :items="$breadcrumbs"/>
                        <x-ui.flash-messages/>
                        <x-ui.validation-summary class="mb-4"/>
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    @else
        <header class="guest-header">
            <nav class="navbar navbar-expand bg-white border-bottom" aria-label="Public navigation">
                <div class="container-xl">
                    <a class="navbar-brand fw-semibold" href="{{ route('home') }}">Event Management</a>
                    @unless (request()->routeIs('login'))
                        <a class="btn btn-primary btn-sm" href="{{ route('login') }}">Log in</a>
                    @endunless
                </div>
            </nav>
        </header>
        <main id="main-content" tabindex="-1">
            <div class="container-xl pt-3">
                <x-ui.flash-messages/>
                <x-ui.validation-summary/>
            </div>
            {{ $slot }}
        </main>
    @endauth

    <x-ui.confirmation-modal/>
</body>
</html>
