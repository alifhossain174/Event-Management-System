<x-layouts.app title="Create booking enquiry" wide :breadcrumbs="[['label' => 'Overview', 'url' => route('home')], ['label' => 'Bookings', 'url' => route('bookings.index')], ['label' => 'Create']]">
    <x-ui.page-header title="Create booking enquiry" subtitle="Capture an optional manager-created request. Approval is one step by default."/>
    <form method="POST" action="{{ route('bookings.store') }}">@csrf @include('bookings._form')</form>
</x-layouts.app>
