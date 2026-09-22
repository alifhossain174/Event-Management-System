<x-layouts.app title="Create Invoice" :breadcrumbs="[['label' => 'Events', 'url' => route('events.index')], ['label' => $event->reference_number, 'url' => route('events.show', $event)], ['label' => 'Invoices', 'url' => route('events.invoices.index', $event)], ['label' => 'Create']]" wide>
    <x-ui.page-header title="Create Invoice" :subtitle="'Create an editable Draft from '.$event->name.' charges. No Booking is required.'"/>
    <div class="alert alert-info">The Event estimate is prefilled as one editable service line. It is not posted as Income; recognized income remains the manual Payment ledger.</div>
    <form method="POST" action="{{ route('events.invoices.store', $event) }}" class="card card-body shadow-sm">
        @csrf
        @include('events.invoices._form')
    </form>
</x-layouts.app>
