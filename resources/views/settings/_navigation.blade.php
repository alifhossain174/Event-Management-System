<x-ui.tabs :items="[
    ['label' => 'Organization', 'url' => route('settings.edit'), 'active' => request()->routeIs('settings.edit')],
    ['label' => 'Branches', 'url' => route('settings.branches.index'), 'active' => request()->routeIs('settings.branches.*')],
    ['label' => 'Master data', 'url' => route('settings.master-data.index', 'event-categories'), 'active' => request()->routeIs('settings.master-data.*')],
]" class="mb-4"/>
