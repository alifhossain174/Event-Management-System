<x-layouts.app
    :title="$moduleDefinition->display_label.' · '.$event->name"
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name, 'url' => route('events.show', $event)],
        ['label' => $moduleDefinition->display_label],
    ]"
>
    <x-ui.page-header :title="$moduleDefinition->display_label" :subtitle="$event->reference_number.' · '.$event->name">
        <x-slot:actions>
            <a class="btn btn-outline-secondary" href="{{ route('events.show', $event) }}">Back to event</a>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="card" aria-labelledby="module-workspace-title">
        <div class="card-body p-4">
            <h2 class="h4" id="module-workspace-title">Module workspace ready</h2>
            <p class="mb-2">This module is enabled for the Event and you are authorized to open it.</p>
            <p class="text-secondary mb-0">Its business records and actions belong to the module's later implementation prompt. No placeholder records are created here.</p>
        </div>
    </section>
</x-layouts.app>
