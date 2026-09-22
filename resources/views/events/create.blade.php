<x-layouts.app
    title="Create event"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => 'Create'],
    ]"
>
    <x-ui.page-header title="Create event" subtitle="Start the core workflow directly from a Client; Booking remains optional."/>
    @foreach ($moduleWarnings as $warning)<div class="alert alert-warning" role="status">{{ $warning }}</div>@endforeach
    <form method="POST" action="{{ route('events.store') }}">
        @csrf
        @include('events._form')
    </form>
</x-layouts.app>
