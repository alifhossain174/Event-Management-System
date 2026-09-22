<x-layouts.app
    :title="'Edit '.$event->name"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name, 'url' => route('events.show', $event)],
        ['label' => 'Edit'],
    ]"
>
    <x-ui.page-header :title="'Edit '.$event->name" :subtitle="$event->reference_number"/>
    <form method="POST" action="{{ route('events.update', $event) }}">
        @csrf @method('PUT')
        @include('events._form')
    </form>
</x-layouts.app>
