<x-ui.tabs :items="[
    ['label' => 'Organization', 'url' => route('settings.edit'), 'active' => request()->routeIs('settings.edit')],
    ['label' => 'Branches', 'url' => route('settings.branches.index'), 'active' => request()->routeIs('settings.branches.*')],
    ['label' => 'Master data', 'url' => route('settings.master-data.index', 'event-categories'), 'active' => request()->routeIs('settings.master-data.*')],
    ['label' => 'Event templates', 'url' => route('settings.event-templates.index'), 'active' => request()->routeIs('settings.event-templates.*')],
    ['label' => 'Module registry', 'url' => route('settings.module-definitions.index'), 'active' => request()->routeIs('settings.module-definitions.*')],
]" class="mb-4"/>
