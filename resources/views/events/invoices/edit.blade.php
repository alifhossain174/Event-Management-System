<x-layouts.app title="Edit Invoice" :breadcrumbs="[['label' => 'Events', 'url' => route('events.index')], ['label' => $event->reference_number, 'url' => route('events.show', $event)], ['label' => 'Invoices', 'url' => route('events.invoices.index', $event)], ['label' => 'Edit']]" wide>
    <x-ui.page-header title="Edit Draft Invoice" subtitle="Only Draft snapshots are editable."/>
    <form method="POST" action="{{ route('events.invoices.update', [$event, $invoice]) }}" class="card card-body shadow-sm">
        @csrf @method('PUT')
        @include('events.invoices._form')
    </form>
</x-layouts.app>
