<x-layouts.app title="Create venue" :breadcrumbs="[['label'=>'Venues','url'=>route('venues.index')],['label'=>'Create']]">
    <x-ui.page-header title="Create venue" subtitle="Create an owned or third-party venue without committing an Event to a fixed price."/>
    <form method="POST" action="{{ route('venues.store') }}">@csrf @include('venues._form')</form>
</x-layouts.app>
